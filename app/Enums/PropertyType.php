<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PropertyType: string
{
    use EnumHelpers;

    case Hostel = 'hostel';
    case House = 'house';
    case Apartment = 'apartment';
    case Room = 'room';
}
