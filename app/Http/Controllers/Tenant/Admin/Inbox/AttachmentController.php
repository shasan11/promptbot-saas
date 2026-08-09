<?php
namespace App\Http\Controllers\Tenant\Admin\Inbox;
use App\Http\Controllers\Controller; use App\Models\Inbox\ConversationAttachment; use App\Models\Inbox\Message; use App\Services\Tenancy\TenantStorageService; use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\File; use Illuminate\Support\Facades\Gate; use Illuminate\Support\Str; use Symfony\Component\HttpFoundation\BinaryFileResponse;
class AttachmentController extends Controller
{
    public function store(Request $request, Message $message, TenantStorageService $storage): RedirectResponse
    {
        Gate::authorize('reply', $message->conversation); $request->validate(['file'=>['required','file','max:20480','mimes:jpg,jpeg,png,gif,pdf,txt,csv,doc,docx,xls,xlsx,zip']]);
        $upload=$request->file('file'); $originalFilename=$upload->getClientOriginalName(); $directory=$storage->path('conversation-attachments/'.$message->conversation_id); File::ensureDirectoryExists($directory); $stored=Str::uuid().'.'.$upload->guessExtension(); $upload->move($directory,$stored);
        $message->attachments()->create(['original_filename'=>$originalFilename,'stored_filename'=>$stored,'mime_type'=>File::mimeType($directory.DIRECTORY_SEPARATOR.$stored),'file_size'=>File::size($directory.DIRECTORY_SEPARATOR.$stored),'storage_disk'=>'tenant_private','storage_path'=>$directory.DIRECTORY_SEPARATOR.$stored,'uploaded_by'=>$request->user('tenant')->id,'checksum'=>hash_file('sha256',$directory.DIRECTORY_SEPARATOR.$stored)]);
        return back()->with('status','Attachment uploaded.');
    }
    public function download(Request $request, ConversationAttachment $attachment): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(),403); Gate::authorize('view',$attachment->message->conversation); abort_unless(File::exists($attachment->storage_path),404);
        return response()->download($attachment->storage_path,$attachment->original_filename,['Content-Type'=>$attachment->mime_type]);
    }
}
