<?php

namespace App\Services\Dicom;

use RuntimeException;

/**
 * Thrown when a patient uploads a file of another study while their current study is still available.
 */
class StudyLimitReachedException extends RuntimeException
{
    //
}
