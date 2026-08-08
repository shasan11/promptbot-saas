<?php

namespace App\Enums\Connections;

enum ResourceType: string
{
    case Folder = 'folder';
    case File = 'file';
    case DocumentCollection = 'document_collection';
    case DatabaseTable = 'database_table';
    case DatabaseView = 'database_view';
    case CrmObject = 'crm_object';
    case HelpdeskTickets = 'helpdesk_tickets';
    case EmailMailbox = 'email_mailbox';
    case Calendar = 'calendar';
    case Channel = 'channel';
    case Repository = 'repository';
    case ApiEndpoint = 'api_endpoint';
    case RemoteCollection = 'remote_collection';
    case Bucket = 'bucket';
    case Sheet = 'sheet';
    case Custom = 'custom';
}
