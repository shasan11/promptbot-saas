<?php

namespace App\Enums\Connections;

enum ConnectionCapability: string
{
    case Test = 'connection.test';
    case ResourceList = 'resource.list';
    case ResourceRead = 'resource.read';
    case ResourceSearch = 'resource.search';
    case ResourceCreate = 'resource.create';
    case ResourceUpdate = 'resource.update';
    case ResourceDelete = 'resource.delete';
    case SyncFull = 'sync.full';
    case SyncIncremental = 'sync.incremental';
    case SyncRealtime = 'sync.realtime';
    case WebhookReceive = 'webhook.receive';
    case WebhookRegister = 'webhook.register';
    case FilesDownload = 'files.download';
    case FilesUpload = 'files.upload';
    case MessagesSend = 'messages.send';
    case RecordsQuery = 'records.query';
    case ActionsExecute = 'actions.execute';
    case AiTool = 'ai.tool';
}
