<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    /**
     * Get all support tickets with filtering
     */
    public function getTickets(array $filters = []): array
    {
        $query = SupportTicket::with(['user', 'assignedTo'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $tickets = $query->paginate(20);

        return [
            'tickets' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ]
        ];
    }

    /**
     * Get ticket details with messages
     */
    public function getTicketDetails(int $ticketId): ?array
    {
        $ticket = SupportTicket::with(['user', 'assignedTo', 'messages.user'])->find($ticketId);
        
        if (!$ticket) {
            return null;
        }

        return [
            'ticket' => $ticket,
            'messages' => $ticket->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'user' => $message->user->name,
                    'message' => $message->message,
                    'is_internal' => $message->is_internal,
                    'attachments' => $message->attachments,
                    'created_at' => $message->created_at,
                ];
            }),
        ];
    }

    /**
     * Create new support ticket
     */
    public function createTicket(int $userId, array $data): array
    {
        try {
            DB::beginTransaction();

            $ticket = SupportTicket::create([
                'user_id' => $userId,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'open',
            ]);

            // Create initial message
            TicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $userId,
                'message' => $data['message'],
                'is_internal' => false,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Support ticket created successfully',
                'ticket_id' => $ticket->id
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to create support ticket: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Assign ticket to admin
     */
    public function assignTicket(int $ticketId, int $adminId): array
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            
            $ticket->update([
                'assigned_to' => $adminId,
                'status' => 'in_progress',
            ]);

            // Add internal message
            TicketMessage::create([
                'support_ticket_id' => $ticketId,
                'user_id' => $adminId,
                'message' => 'Ticket assigned to ' . User::find($adminId)->name,
                'is_internal' => true,
            ]);

            return [
                'success' => true,
                'message' => 'Ticket assigned successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to assign ticket: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add message to ticket
     */
    public function addMessage(int $ticketId, int $userId, string $message, bool $isInternal = false): array
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);

            TicketMessage::create([
                'support_ticket_id' => $ticketId,
                'user_id' => $userId,
                'message' => $message,
                'is_internal' => $isInternal,
            ]);

            // Update ticket status if it was resolved
            if ($ticket->status === 'resolved') {
                $ticket->update(['status' => 'in_progress']);
            }

            return [
                'success' => true,
                'message' => 'Message added successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to add message: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Resolve ticket
     */
    public function resolveTicket(int $ticketId, int $adminId, string $resolutionMessage = null): array
    {
        try {
            DB::beginTransaction();

            $ticket = SupportTicket::findOrFail($ticketId);
            
            $ticket->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            if ($resolutionMessage) {
                TicketMessage::create([
                    'support_ticket_id' => $ticketId,
                    'user_id' => $adminId,
                    'message' => $resolutionMessage,
                    'is_internal' => false,
                ]);
            }

            // Add internal resolution note
            TicketMessage::create([
                'support_ticket_id' => $ticketId,
                'user_id' => $adminId,
                'message' => 'Ticket resolved by ' . User::find($adminId)->name,
                'is_internal' => true,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Ticket resolved successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to resolve ticket: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Close ticket
     */
    public function closeTicket(int $ticketId, int $adminId): array
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            
            $ticket->update(['status' => 'closed']);

            // Add internal closure note
            TicketMessage::create([
                'support_ticket_id' => $ticketId,
                'user_id' => $adminId,
                'message' => 'Ticket closed by ' . User::find($adminId)->name,
                'is_internal' => true,
            ]);

            return [
                'success' => true,
                'message' => 'Ticket closed successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to close ticket: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get support statistics
     */
    public function getSupportStatistics(): array
    {
        $totalTickets = SupportTicket::count();
        $resolvedTickets = SupportTicket::where('status', 'resolved')->count();

        return [
            'total_tickets' => $totalTickets,
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'in_progress_tickets' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved_tickets' => $resolvedTickets,
            'closed_tickets' => SupportTicket::where('status', 'closed')->count(),
            'resolution_rate' => $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 2) : 0,
            'avg_resolution_time' => $this->calculateAverageResolutionTime(),
            'tickets_by_category' => $this->getTicketsByCategory(),
            'tickets_by_priority' => $this->getTicketsByPriority(),
        ];
    }

    /**
     * Calculate average resolution time in hours
     */
    private function calculateAverageResolutionTime(): float
    {
        $resolvedTickets = SupportTicket::whereNotNull('resolved_at')->get();
        if ($resolvedTickets->isEmpty()) return 0;

        $totalHours = $resolvedTickets->sum(function ($ticket) {
            return $ticket->created_at->diffInHours($ticket->resolved_at);
        });

        return round($totalHours / $resolvedTickets->count(), 1);
    }

    /**
     * Get tickets grouped by category
     */
    private function getTicketsByCategory(): array
    {
        return SupportTicket::select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
    }

    /**
     * Get tickets grouped by priority
     */
    private function getTicketsByPriority(): array
    {
        return SupportTicket::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();
    }
}