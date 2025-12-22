<?php

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class UniqueUserEmail extends Constraint
{
    public string $message = 'Cet email "{{ value }}" est déjà utilisé.';
    
    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
