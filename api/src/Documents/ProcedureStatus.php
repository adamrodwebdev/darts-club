<?php

namespace App\Documents;

/** Où en est une démarche. */
enum ProcedureStatus: string
{
    case Todo = 'a-faire';
    case Ready = 'prete';
    case Sent = 'envoyee';
    case Received = 'recue';
    case NotNeeded = 'pas-necessaire';
}
