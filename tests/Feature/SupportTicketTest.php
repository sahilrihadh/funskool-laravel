<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Models\SupportTicket;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Services\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
    }

    protected function contact(array $overrides = [])
    {
        return $this->post(route('support.store'), [
            'name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'subject' => 'Where is my parcel?',
            'category' => 'delivery',
            'message' => 'It has been a week.',
            ...$overrides,
        ]);
    }

    public function test_a_guest_can_open_a_ticket_and_follow_it_with_the_emailed_link(): void
    {
        $this->contact(['attachments' => [UploadedFile::fake()->image('box.jpg')]])->assertRedirect();

        $ticket = SupportTicket::query()->sole();
        $this->assertSame('open', $ticket->status);
        $this->assertCount(1, $ticket->messages->first()->attachments);
        Notification::assertSentOnDemand(TicketCreatedNotification::class);

        $this->get($ticket->guestUrl())->assertOk()->assertSee('Where is my parcel?');
        $this->get(route('support.guest', ['ticket' => $ticket, 'token' => 'wrong']))->assertNotFound();

        $this->post(route('support.guest.reply', ['ticket' => $ticket, 'token' => $ticket->access_token]), ['body' => 'Any update?'])->assertRedirect();
        $this->assertSame(2, $ticket->messages()->count());
    }

    public function test_honeypot_submissions_are_dropped(): void
    {
        $this->contact(['website' => 'http://spam.test'])->assertRedirect(route('home'));
        $this->assertSame(0, SupportTicket::query()->count());
    }

    public function test_customers_only_see_their_own_tickets(): void
    {
        $owner = $this->customer();
        $this->actingAs($owner)->contact(['email' => $owner->email]);
        $ticket = SupportTicket::query()->sole();

        $this->actingAs($owner)->get(route('support.show', $ticket))->assertOk();
        $this->actingAs($this->customer())->get(route('support.show', $ticket))->assertForbidden();
        $this->actingAs($this->customer())->getJson("/api/support-tickets/{$ticket->ticket_number}")->assertForbidden();
    }

    public function test_staff_reply_emails_the_customer_and_internal_notes_stay_private(): void
    {
        $this->contact();
        $ticket = SupportTicket::query()->sole();
        $agent = $this->staff('support');

        Livewire::actingAs($agent)
            ->test(ViewSupportTicket::class, ['record' => $ticket->ticket_number])
            ->fillForm(['body' => 'Checked with the courier — note only', 'internal' => true])
            ->call('sendReply')
            ->assertHasNoFormErrors()
            ->fillForm(['body' => 'Your parcel is out for delivery today.', 'status' => 'solved', 'internal' => false])
            ->call('sendReply');

        $ticket->refresh();
        $this->assertSame('solved', $ticket->status);
        $this->assertTrue($ticket->assignee->is($agent));
        $this->assertNotNull($ticket->first_response_at);
        Notification::assertSentOnDemandTimes(TicketRepliedNotification::class, 1);

        $this->get($ticket->guestUrl())
            ->assertSee('out for delivery')
            ->assertDontSee('note only');

        // A customer reply reopens a solved ticket.
        app(SupportService::class)->customerReply($ticket, 'Got it, thanks — but one item is missing.');
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_attachments_are_only_served_to_people_allowed_to_see_them(): void
    {
        $this->contact(['attachments' => [UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')]]);
        $ticket = SupportTicket::query()->sole();
        $message = $ticket->messages->first();

        $this->get(route('support.attachment', [$message, 0]))->assertForbidden();
        $this->get(route('support.attachment', [$message, 0, 'token' => $ticket->access_token]))->assertOk();
        $this->actingAs($this->customer())->get(route('support.attachment', [$message, 0]))->assertForbidden();
        $this->actingAs($this->staff('support'))->get(route('support.attachment', [$message, 0]))->assertOk();
    }

    public function test_connected_websites_can_open_tickets(): void
    {
        [$channel, $key] = $this->channel();

        $this->withToken($key)->postJson('/api/channel/v1/support-tickets', [
            'name' => 'Ravi', 'email' => 'ravi@example.com', 'subject' => 'Gift wrap?', 'message' => 'Do you gift wrap?',
        ])->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonStructure(['customer_url']);

        $ticket = SupportTicket::query()->sole();
        $this->assertTrue($ticket->salesChannel->is($channel));

        [, $otherKey] = $this->channel(['name' => 'Other']);
        $this->withToken($otherKey)->getJson("/api/channel/v1/support-tickets/{$ticket->ticket_number}")->assertNotFound();
    }

    public function test_admin_support_pages_render(): void
    {
        $this->contact();
        $ticket = SupportTicket::query()->sole();
        $admin = $this->staff('admin');

        foreach (['/admin/support-tickets', '/admin/support-tickets/create', "/admin/support-tickets/{$ticket->ticket_number}"] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        $this->actingAs($this->staff('marketing'))->get('/admin/support-tickets')->assertForbidden();
    }
}
