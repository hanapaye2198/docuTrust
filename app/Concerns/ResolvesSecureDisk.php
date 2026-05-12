<?php

namespace App\Concerns;

trait ResolvesSecureDisk
{
    protected function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

    protected function archiveDiskName(): string
    {
        return (string) config('filesystems.docutrust_archive_disk', $this->secureDiskName());
    }
}
