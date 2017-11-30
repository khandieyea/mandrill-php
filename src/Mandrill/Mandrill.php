<?php

declare(strict_types=1);
namespace Mandrill;

use Mandrill\Exception\Exception;
use Mandrill\Exception\HttpException;
use Mandrill\Exception\InvalidCustomDNSException;
use Mandrill\Exception\InvalidDeleteDefaultPoolException;
use Mandrill\Exception\InvalidDeleteNonEmptyPoolException;
use Mandrill\Exception\InvalidEmptyDefaultPoolException;
use Mandrill\Exception\InvalidKeyException;
use Mandrill\Exception\InvalidRejectException;
use Mandrill\Exception\InvalidTagNameException;
use Mandrill\Exception\InvalidTemplateException;
use Mandrill\Exception\LimitCustomDNSException;
use Mandrill\Exception\LimitIPProvisionException;
use Mandrill\Exception\LimitMetadataException;
use Mandrill\Exception\LimitReputationException;
use Mandrill\Exception\ServiceUnavailableException;
use Mandrill\Exception\SubscriptionException;
use Mandrill\Exception\UnknownExportException;
use Mandrill\Exception\UnknownHistoryException;
use Mandrill\Exception\UnknownInboundDomainException;
use Mandrill\Exception\UnknownInboundRouteException;
use Mandrill\Exception\UnknownIPException;
use Mandrill\Exception\UnknownMessageException;
use Mandrill\Exception\UnknownMetadataFieldException;
use Mandrill\Exception\UnknownPoolException;
use Mandrill\Exception\UnknownSenderException;
use Mandrill\Exception\UnknownSubaccountException;
use Mandrill\Exception\UnknownTemplateException;
use Mandrill\Exception\UnknownTrackingDomainException;
use Mandrill\Exception\UnknownURLException;
use Mandrill\Exception\UnknownWebhookException;
use Mandrill\Exception\ValidationError;

class Mandrill
{
    public $apikey;
    public $ch;
    public $root = 'https://mandrillapp.com/api/1.0';
    public $debug = false;

    public function __construct($apikey = null)
    {
        if (!$apikey) {
            $apikey = getenv('MANDRILL_APIKEY');
        }
        if (!$apikey) {
            $apikey = $this->readConfigs();
        }
        if (!$apikey) {
            throw new InvalidKeyException('You must provide a Mandrill API key');
        }
        $this->apikey = $apikey;

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mandrill-PHP/1.0.55');
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 600);

        $this->root = rtrim($this->root, '/') . '/';

        $this->templates = new Templates($this);
        $this->exports = new Exports($this);
        $this->users = new Users($this);
        $this->rejects = new Rejects($this);
        $this->inbound = new Inbound($this);
        $this->tags = new Tags($this);
        $this->messages = new Messages($this);
        $this->whitelists = new Whitelists($this);
        $this->ips = new Ips($this);
        $this->internal = new Internal($this);
        $this->subaccounts = new Subaccounts($this);
        $this->urls = new Urls($this);
        $this->webhooks = new Webhooks($this);
        $this->senders = new Senders($this);
        $this->metadata = new Metadata($this);
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function call($url, $params)
    {
        $params['key'] = $this->apikey;
        $params = json_encode($params);
        $ch = $this->ch;

        curl_setopt($ch, CURLOPT_URL, $this->root . $url . '.json');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);

        $start = microtime(true);
        $this->log('Call to ' . $this->root . $url . '.json: ' . $params);
        if ($this->debug) {
            $curl_buffer = fopen('php://memory', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $curl_buffer);
        }

        $response_body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $time = microtime(true) - $start;
        if ($this->debug) {
            rewind($curl_buffer);
            $this->log(stream_get_contents($curl_buffer));
            fclose($curl_buffer);
        }
        $this->log('Completed in ' . number_format($time * 1000, 2) . 'ms');
        $this->log('Got response: ' . $response_body);

        if (curl_error($ch)) {
            throw new Exception("API call to $url failed: " . curl_error($ch));
        }
        $result = json_decode($response_body, true);
        if ($result === null) {
            throw new Exception('We were unable to decode the JSON response from the Mandrill API: ' . $response_body);
        }

        if (floor($info['http_code'] / 100) >= 4) {
            throw $this->castError($result);
        }

        return $result;
    }

    public function readConfigs()
    {
        $paths = array('~/.mandrill.key', '/etc/mandrill.key');
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $apikey = trim(file_get_contents($path));
                if ($apikey) {
                    return $apikey;
                }
            }
        }
        return false;
    }

    public static $error_map = [
        'Invalid_CustomDNS' =>          InvalidCustomDNSException::class,
        'Invalid_DeleteDefaultPool' =>  InvalidDeleteDefaultPoolException::class,
        'Invalid_DeleteNonEmptyPool' => InvalidDeleteNonEmptyPoolException::class,
        'Invalid_EmptyDefaultPool' =>   InvalidEmptyDefaultPoolException::class,
        'Invalid_Key' =>                InvalidKeyException::class,
        'Invalid_Reject' =>             InvalidRejectException::class,
        'Invalid_Tag_Name' =>           InvalidTagNameException::class,
        'Invalid_Template' =>           InvalidTemplateException::class,
        'Invalid_CustomDNSPending' =>   LimitCustomDNSException::class,
        'IP_ProvisionLimit' =>          LimitIPProvisionException::class,
        'Metadata_FieldLimit' =>        LimitMetadataException::class,
        'PoorReputation' =>             LimitReputationException::class,
        'ServiceUnavailable' =>         ServiceUnavailableException::class,
        'PaymentRequired' =>            SubscriptionException::class,
        'Unknown_Export' =>             UnknownExportException::class,
        'NoSendingHistory' =>           UnknownHistoryException::class,
        'Unknown_InboundDomain' =>      UnknownInboundDomainException::class,
        'Unknown_InboundRoute' =>       UnknownInboundRouteException::class,
        'Unknown_IP' =>                 UnknownIPException::class,
        'Unknown_Message' =>            UnknownMessageException::class,
        'Unknown_MetadataField' =>      UnknownMetadataFieldException::class,
        'Unknown_Pool' =>               UnknownPoolException::class,
        'Unknown_Sender' =>             UnknownSenderException::class,
        'Unknown_Subaccount' =>         UnknownSubaccountException::class,
        'Unknown_Template' =>           UnknownTemplateException::class,
        'Unknown_TrackingDomain' =>     UnknownTrackingDomainException::class,
        'Unknown_Url' =>                UnknownURLException::class,
        'Unknown_Webhook' =>            UnknownWebhookException::class,
        'ValidationError' =>            ValidationException::class,
    ];

    public function castError($result): Exception
    {
        if ($result['status'] !== 'error' || !$result['name']) {
            throw new UnexpectedException(json_encode($result));
        }

        $class = self::$error_map[$result['name']] ?? Exception::class;
        return new $class($result['message'], $result['code']);
    }

    public function log(string $msg)
    {
        if ($this->debug) {
            error_log($msg);
        }
    }
}
