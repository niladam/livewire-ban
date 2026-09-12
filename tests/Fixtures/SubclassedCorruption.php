<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

class SubclassedCorruption extends CorruptComponentPayloadException {}
