<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{if(Schema::hasTable('platform_settings'))DB::table('platform_settings')->where('group','ai_rag')->delete();Schema::dropIfExists('ai_model_configs');}public function down():void{/* The non-AI commercial edition intentionally does not restore legacy provider credentials. */}};
