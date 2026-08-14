<?php

namespace App\Domain\CivilinaAI\Enums;

enum AnswerabilityStatus: string
{
    case CanAnswer = 'CAN_ANSWER';
    case NeedClarification = 'NEED_CLARIFICATION';
    case NeedData = 'NEED_DATA';
    case NeedDocument = 'NEED_DOCUMENT';
    case ExternalVerificationRequired = 'EXTERNAL_VERIFICATION_REQUIRED';
    case HumanExpertRecommended = 'HUMAN_EXPERT_RECOMMENDED';
    case OutOfScope = 'OUT_OF_SCOPE';
}
