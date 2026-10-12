<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum EducationLevel: string
{
    case Sd = 'sd';
    case Smp = 'smp';
    case Sma = 'sma';
    case Diploma = 'diploma';
    case S1 = 's1';
    case S2 = 's2';
    case S3 = 's3';

    /** The wording of the registration form's "Tingkat Pendidikan" choices. */
    public function label(): string
    {
        return match ($this) {
            self::Sd => 'SD',
            self::Smp => 'SMP',
            self::Sma => 'SMA/SMK',
            self::Diploma => 'Diploma (D1-D4)',
            self::S1 => 'S1',
            self::S2 => 'S2',
            self::S3 => 'S3',
        };
    }
}
