<?php
return ['version'=>trim((string)@file_get_contents(base_path('VERSION')))?:'1.0.0','demo_mode'=>env('DEMO_MODE',false),'demo_tenant_ids'=>array_values(array_filter(array_map('trim',explode(',',env('DEMO_TENANT_IDS',''))))),'support_url'=>env('PROMPTBOT_SUPPORT_URL',''),'docs_url'=>env('PROMPTBOT_DOCS_URL','')];
