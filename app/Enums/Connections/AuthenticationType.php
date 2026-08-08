<?php

namespace App\Enums\Connections;

enum AuthenticationType: string
{
    case OAuth2 = 'oauth2';
    case ApiKey = 'api_key';
    case BearerToken = 'bearer_token';
    case Basic = 'basic';
    case UsernamePassword = 'username_password';
    case ServiceAccount = 'service_account';
    case AwsKeys = 'aws_keys';
    case SshKey = 'ssh_key';
    case ClientCertificate = 'client_certificate';
    case DatabaseCredentials = 'database_credentials';
    case CustomHeaders = 'custom_headers';
    case None = 'none';
}
