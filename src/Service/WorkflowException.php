<?php

namespace App\Service;

/**
 * Transition refusée par les règles métier. Le message est destiné à l'administrateur.
 */
class WorkflowException extends \DomainException
{
}
