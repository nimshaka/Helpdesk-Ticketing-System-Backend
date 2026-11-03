<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\ApiTestCase;

class TicketAttachmentTest extends ApiTestCase
{
    /** @var array<string, mixed> */
    private array $lastLinkOptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['helpdesk.attachment_disk' => 'r2']);
        Storage::fake('r2');

        $captured = &$this->lastLinkOptions;

        $this->disk()->buildTemporaryUrlsUsing(
            function (string $path, $expiration, array $options = []) use (&$captured): string {
                $captured = $options;

                return "https://files.test/{$path}";
            }
        );
    }

    public function test_attachment_cannot_be_read_through_another_tickets_url(): void
    {
        $customer = $this->customer();
        $first = $this->ticketFor($customer);
        $second = $this->ticketFor($customer);
        $attachment = $this->storedAttachment($first);

        $this->assertErrorShape(
            $this->asUser($customer)->getJson("/api/tickets/{$second->id}/attachments/{$attachment->id}"),
            404,
        );
    }

    public function test_attachments_appear_in_the_ticket_detail_without_the_storage_key(): void
    {
        $customer = $this->customer();
        $ticket = $this->ticketFor($customer);
        $this->storedAttachment($ticket, 'photo.jpg', 'image/jpeg');
        $this->storedAttachment($ticket);

        $response = $this->asUser($customer)->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.attachments')
            ->assertJsonPath('data.attachments.0.name', 'photo.jpg')
            ->assertJsonPath('data.attachments.0.is_image', true)
            ->assertJsonPath('data.attachments.1.is_pdf', true);

        $this->assertStringNotContainsString('ticket-attachments/', $response->getContent());
    }

    public function test_customer_creates_a_ticket_with_an_image_and_a_pdf(): void
    {
        $customer = $this->customer();

        $response = $this->asUser($customer)->postJson('/api/tickets', $this->payload([
            'attachments' => [
                UploadedFile::fake()->image('photo.jpg', 600, 400),
                UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf'),
            ],
        ]))->assertCreated()->assertJsonCount(2, 'data.attachments');

        $attachments = TicketAttachment::query()->orderBy('id')->get();

        $this->assertSame(['photo.jpg', 'invoice.pdf'], $attachments->pluck('original_name')->all());
        $this->assertSame($customer->id, $attachments[0]->uploaded_by);

        foreach ($attachments as $attachment) {
            $this->disk()->assertExists($attachment->path);
        }

        $this->assertStringNotContainsString('ticket-attachments/', $response->getContent());
    }

    public function test_download_flag_changes_the_content_disposition(): void
    {
        $customer = $this->customer();
        $ticket = $this->ticketFor($customer);
        $attachment = $this->storedAttachment($ticket);
        $url = "/api/tickets/{$ticket->id}/attachments/{$attachment->id}";
        $this->asUser($customer);

        $this->lastLinkOptions = [];
        $this->getJson($url)->assertOk()->assertJsonPath('data.download', false);
        $this->assertArrayHasKey('ResponseContentDisposition', $this->lastLinkOptions);
        $this->assertStringStartsWith('inline;', $this->lastLinkOptions['ResponseContentDisposition']);

        $this->lastLinkOptions = [];
        $this->getJson("{$url}?download=1")->assertOk()->assertJsonPath('data.download', true);
        $this->assertArrayHasKey('ResponseContentDisposition', $this->lastLinkOptions);
        $this->assertStringStartsWith('attachment;', $this->lastLinkOptions['ResponseContentDisposition']);
        $this->assertStringContainsString('filename="invoice.pdf"', $this->lastLinkOptions['ResponseContentDisposition']);

        $this->assertErrorShape($this->getJson("{$url}?download=banana"), 422);
    }

    public function test_files_of_the_wrong_type_or_size_are_rejected_and_nothing_is_saved(): void
    {
        $this->asUser($this->customer());

        $cases = [
            'program' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
            'text file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'html pretending to be a pdf' => UploadedFile::fake()->create('evil.pdf', 10, 'text/html'),
            'too large' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'),
        ];

        foreach ($cases as $label => $file) {
            $response = $this->postJson('/api/tickets', $this->payload(['attachments' => [$file]]));

            $this->assertErrorShape($response, 422);
            $this->assertArrayHasKey('attachments.0', $response->json('error.details'), $label);
        }

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_more_than_five_files_are_rejected(): void
    {
        $files = [];

        foreach (range(1, 6) as $number) {
            $files[] = UploadedFile::fake()->image("photo{$number}.jpg");
        }

        $this->assertErrorShape(
            $this->asUser($this->customer())->postJson('/api/tickets', $this->payload(['attachments' => $files])),
            422,
        );
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_only_agents_who_hold_the_ticket_and_admins_can_open_an_assigned_ticket_attachment(): void
    {
        $customer = $this->customer();
        $holder = $this->agent();
        $ticket = $this->heldTicket($customer, $holder);
        $attachment = $this->storedAttachment($ticket);
        $url = "/api/tickets/{$ticket->id}/attachments/{$attachment->id}";

        $this->assertErrorShape($this->asUser($this->agent())->getJson($url), 404);
        $this->asUser($holder)->getJson($url)->assertOk();
        $this->asUser($this->admin())->getJson($url)->assertOk();
    }

    public function test_the_ticket_is_not_saved_and_files_are_removed_when_the_database_fails(): void
    {
        Ticket::creating(fn () => throw new \RuntimeException('Boom'));

        $this->asUser($this->customer())->postJson('/api/tickets', $this->payload([
            'attachments' => [UploadedFile::fake()->image('photo.jpg')],
        ]))->assertStatus(500);

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_who_can_open_an_attachment_link(): void
    {
        $customer = $this->customer();
        $ticket = $this->ticketFor($customer);
        $attachment = $this->storedAttachment($ticket);
        $url = "/api/tickets/{$ticket->id}/attachments/{$attachment->id}";

        $this->getJson($url)->assertStatus(401);

        $this->asUser($customer)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.url', 'https://files.test/'.$attachment->path)
            ->assertJsonPath('data.name', 'invoice.pdf');

        $this->assertErrorShape($this->asUser($this->customer())->getJson($url), 404);
        $this->asUser($this->agent())->getJson($url)->assertOk();
        $this->asUser($this->admin())->getJson($url)->assertOk();
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('r2');

        return $disk;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'subject' => 'Printer is not working',
            'description' => 'See the attached files.',
            'priority' => 'high',
            ...$overrides,
        ];
    }

    private function storedAttachment(Ticket $ticket, string $name = 'invoice.pdf', string $mime = 'application/pdf'): TicketAttachment
    {
        $path = 'ticket-attachments/2026-10/'.fake()->uuid().'.'.pathinfo($name, PATHINFO_EXTENSION);
        $this->disk()->put($path, 'file contents');

        return $ticket->attachments()->create([
            'uploaded_by' => $ticket->customer_id,
            'original_name' => $name,
            'path' => $path,
            'mime_type' => $mime,
            'size_bytes' => 13,
        ]);
    }
}