<?php

/**
 * Override Laravel's storage_path helper for testing
 */
if (! function_exists('storage_path')) {
    function storage_path($path = '')
    {
        $storagePath = base_path('storage');

        return $path ? $storagePath.DIRECTORY_SEPARATOR.$path : $storagePath;
    }
}
