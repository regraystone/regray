<?php

namespace App\Enums;

/**
 * Logistics' status, which starts once sales has uploaded the job order:
 * order received, measure, sourcing, production, quality check, ready, dispatched,
 * installation (when the job has one) and completed. On hold and delayed can
 * be entered from any active step.
 */
enum ProcessStatus: string
{
    case OrderReceived = 'order_received';
    case Measure = 'measure';
    case Sourcing = 'sourcing';
    case Production = 'production';
    case QualityCheck = 'quality_check';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Installation = 'installation';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Delayed = 'delayed';
}
