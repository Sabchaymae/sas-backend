<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case ASSISTANT = 'assistant';
    case ANIMATEUR = 'animateur';
    case SUPERVISEUR = 'superviseur';
}
