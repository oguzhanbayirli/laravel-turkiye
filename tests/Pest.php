<?php

use OguzhanBayirli\Turkiye\Tests\TestCase;

uses(TestCase::class)->in('Feature');

function load_fixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/'.$name);
}
