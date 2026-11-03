<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Support\StoredAttachment;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TicketAttachmentService
{
    /**
     * Deletes files that were uploaded but never saved in the database.
     *
     * @param  list<StoredAttachment>  $stored
     */
    public function discardAttachmentsService(array $stored): void
    {
        foreach ($stored as $file) {
            try {
                $this->disk()->delete($file->path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Saves one database row per uploaded file. Call it inside the ticket transaction.
     *
     * @param  list<StoredAttachment>  $stored
     */
    public function recordAttachmentsService(Ticket $ticket, User $uploader, array $stored): void
    {
        foreach ($stored as $file) {
            $ticket->attachments()->create([
                'uploaded_by' => $uploader->id,
                'original_name' => $file->originalName,
                'path' => $file->path,
                'mime_type' => $file->mimeType,
                'size_bytes' => $file->size,
            ]);
        }
    }

    /**
     * A short-lived link the browser can open directly. Check permission before calling this.
     *
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function temporaryAttachmentUrlService(TicketAttachment $attachment, bool $download): array
    {
        $expiresAt = now()->addMinutes((int) config('helpdesk.attachment_url_minutes'));

        $url = $this->disk()->temporaryUrl($attachment->path, $expiresAt, [
            'ResponseContentType' => $attachment->mime_type,
            'ResponseContentDisposition' => $this->contentDisposition($attachment->original_name, $download),
        ]);

        return ['url' => $url, 'expires_at' => $expiresAt];
    }

    /**
     * Uploads every file, or none: a failure removes the files already uploaded.
     *
     * @param  list<UploadedFile>  $files
     * @return list<StoredAttachment>
     */
    public function uploadAttachmentsService(array $files): array
    {
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->uploadOne($file);
            }
        } catch (Throwable $exception) {
            $this->discardAttachmentsService($stored);

            throw $exception;
        }

        return $stored;
    }

    private function cleanName(string $original, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $original));
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\"]+/u', '', $name) ?? '';
        $name = Str::limit(trim($name), 120, '');

        return $name === '' ? "attachment.{$extension}" : $name;
    }

    private function contentDisposition(string $name, bool $download): string
    {
        $type = $download ? 'attachment' : 'inline';
        $ascii = str_replace(['"', '\\'], '', Str::ascii($name));

        return "{$type}; filename=\"{$ascii}\"; filename*=UTF-8''".rawurlencode($name);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('helpdesk.attachment_disk'));
    }

    private function uploadOne(UploadedFile $file): StoredAttachment
    {
        // The extension and type come from the file's content, never from the browser's claim.
        $extension = $file->extension() ?: 'bin';
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $size = (int) $file->getSize();
        $name = Str::uuid()->toString().'.'.$extension;

        $path = $this->disk()->putFileAs('ticket-attachments/'.now()->format('Y-m'), $file, $name);

        if ($path === false) {
            throw new RuntimeException('The attachment could not be stored.');
        }

        return new StoredAttachment(
            $path,
            $this->cleanName($file->getClientOriginalName(), $extension),
            $mimeType,
            $size,
        );
    }
}