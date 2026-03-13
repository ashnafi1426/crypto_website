<?php

namespace App\Services;

use App\Models\User;
use App\Models\KycDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class KycManagementService
{
    /**
     * Get all KYC submissions with filtering
     */
    public function getKycSubmissions(array $filters = []): array
    {
        $query = KycDocument::with(['user'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $documents = $query->paginate(20);

        return [
            'documents' => $documents->items(),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ];
    }

    /**
     * Get KYC document details
     */
    public function getKycDocument(int $documentId): ?KycDocument
    {
        return KycDocument::with(['user', 'verifiedBy'])->find($documentId);
    }

    /**
     * Approve KYC document
     */
    public function approveKyc(int $documentId, int $adminId): array
    {
        try {
            DB::beginTransaction();

            $document = KycDocument::findOrFail($documentId);
            
            $document->update([
                'status' => 'approved',
                'verified_by' => $adminId,
                'verified_at' => now(),
                'expires_at' => now()->addYears(2), // Documents expire after 2 years
            ]);

            // Update user KYC status
            $document->user->update([
                'kyc_status' => 'approved',
                'kyc_approved_at' => now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'KYC document approved successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to approve KYC document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reject KYC document
     */
    public function rejectKyc(int $documentId, int $adminId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $document = KycDocument::findOrFail($documentId);
            
            $document->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'verified_by' => $adminId,
                'verified_at' => now(),
            ]);

            // Update user KYC status
            $document->user->update([
                'kyc_status' => 'rejected',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'KYC document rejected successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to reject KYC document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get KYC statistics
     */
    public function getKycStatistics(): array
    {
        return [
            'total_submissions' => KycDocument::count(),
            'pending_review' => KycDocument::where('status', 'pending')->count(),
            'approved' => KycDocument::where('status', 'approved')->count(),
            'rejected' => KycDocument::where('status', 'rejected')->count(),
            'expired' => KycDocument::where('status', 'expired')->count(),
            'approval_rate' => $this->calculateApprovalRate(),
            'avg_processing_time' => $this->calculateAverageProcessingTime(),
        ];
    }

    /**
     * Calculate approval rate
     */
    private function calculateApprovalRate(): float
    {
        $total = KycDocument::whereIn('status', ['approved', 'rejected'])->count();
        if ($total === 0) return 0;

        $approved = KycDocument::where('status', 'approved')->count();
        return round(($approved / $total) * 100, 2);
    }

    /**
     * Calculate average processing time in hours
     */
    private function calculateAverageProcessingTime(): float
    {
        $processed = KycDocument::whereNotNull('verified_at')->get();
        if ($processed->isEmpty()) return 0;

        $totalHours = $processed->sum(function ($doc) {
            return $doc->created_at->diffInHours($doc->verified_at);
        });

        return round($totalHours / $processed->count(), 1);
    }

    /**
     * Store uploaded KYC documents
     */
    public function storeKycDocuments(int $userId, array $data, array $files): array
    {
        try {
            DB::beginTransaction();

            $documentPaths = [];

            // Store document front
            if (isset($files['document_front'])) {
                $documentPaths['document_front_path'] = $this->storeFile($files['document_front'], 'kyc/documents');
            }

            // Store document back (optional)
            if (isset($files['document_back'])) {
                $documentPaths['document_back_path'] = $this->storeFile($files['document_back'], 'kyc/documents');
            }

            // Store selfie
            if (isset($files['selfie'])) {
                $documentPaths['selfie_path'] = $this->storeFile($files['selfie'], 'kyc/selfies');
            }

            $kycDocument = KycDocument::create([
                'user_id' => $userId,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                ...$documentPaths,
                'status' => 'pending',
            ]);

            // Update user KYC status
            User::where('id', $userId)->update(['kyc_status' => 'pending']);

            DB::commit();

            return [
                'success' => true,
                'message' => 'KYC documents uploaded successfully',
                'document_id' => $kycDocument->id
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to upload KYC documents: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Store uploaded file
     */
    private function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }
}