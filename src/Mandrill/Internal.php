<?php

declare(strict_types=1);
namespace Mandrill;

class Internal {
    public function __construct(Mandrill $master) {
        $this->master = $master;
    }
}
