<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Docs\AddDocumentComment;
use App\Agents\Tools\Docs\CreateDocument;
use App\Agents\Tools\Docs\DeleteDocument;
use App\Agents\Tools\Docs\DeleteDocumentComment;
use App\Agents\Tools\Docs\GetDocument;
use App\Agents\Tools\Docs\GetDocumentTree;
use App\Agents\Tools\Docs\ListDocumentAttachments;
use App\Agents\Tools\Docs\ListDocumentComments;
use App\Agents\Tools\Docs\ListDocuments;
use App\Agents\Tools\Docs\ListDocumentVersions;
use App\Agents\Tools\Docs\ResolveDocumentComment;
use App\Agents\Tools\Docs\RestoreDocumentVersion;
use App\Agents\Tools\Docs\SearchDocuments;
use App\Agents\Tools\Docs\UpdateDocument;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\Memory\DocumentIndexingService;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers document workspace tools.
 *
 * Document actions share folder/document permissions, while semantic search has
 * an extra dependency on the indexing service to query embedded chunks.
 */
class DocsToolProvider implements BuiltInToolProvider
{
    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    public function groupName(): string
    {
        return 'docs';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list, get, tree, search, create, update, delete, comment',
            'description' => 'Document workspace',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:file-text';
    }

    public function tools(): array
    {
        return [
            'list_documents' => [
                'class' => ListDocuments::class,
                'type' => 'read',
                'name' => 'List Documents',
                'description' => 'List documents and folders, optionally filtered by parent folder.',
                'icon' => 'ph:folder-open',
            ],
            'get_document' => [
                'class' => GetDocument::class,
                'type' => 'read',
                'name' => 'Get Document',
                'description' => 'Get full details and content of a specific document.',
                'icon' => 'ph:file-text',
            ],
            'get_document_tree' => [
                'class' => GetDocumentTree::class,
                'type' => 'read',
                'name' => 'Get Document Tree',
                'description' => 'Get the full document tree hierarchy.',
                'icon' => 'ph:tree-structure',
            ],
            'search_documents' => [
                'class' => SearchDocuments::class,
                'type' => 'read',
                'name' => 'Search Documents',
                'description' => 'Search workspace documents by keyword or semantic similarity.',
                'icon' => 'ph:magnifying-glass',
            ],
            'create_document' => [
                'class' => CreateDocument::class,
                'type' => 'write',
                'name' => 'Create Document',
                'description' => 'Create a new document or folder.',
                'icon' => 'ph:file-plus',
            ],
            'update_document' => [
                'class' => UpdateDocument::class,
                'type' => 'write',
                'name' => 'Update Document',
                'description' => 'Update a document\'s title, content, or parent folder.',
                'icon' => 'ph:file-text',
            ],
            'delete_document' => [
                'class' => DeleteDocument::class,
                'type' => 'write',
                'name' => 'Delete Document',
                'description' => 'Delete a document or folder.',
                'icon' => 'ph:file-minus',
            ],
            'add_document_comment' => [
                'class' => AddDocumentComment::class,
                'type' => 'write',
                'name' => 'Add Document Comment',
                'description' => 'Add a comment to a document.',
                'icon' => 'ph:chat-teardrop-text',
            ],
            'list_document_comments' => [
                'class' => ListDocumentComments::class,
                'type' => 'read',
                'name' => 'List Document Comments',
                'description' => 'List comments on a document with author and resolved status.',
                'icon' => 'ph:chat-teardrop-text',
            ],
            'resolve_document_comment' => [
                'class' => ResolveDocumentComment::class,
                'type' => 'write',
                'name' => 'Resolve Document Comment',
                'description' => 'Mark a document comment as resolved.',
                'icon' => 'ph:chat-teardrop-text',
            ],
            'delete_document_comment' => [
                'class' => DeleteDocumentComment::class,
                'type' => 'write',
                'name' => 'Delete Document Comment',
                'description' => 'Delete a comment from a document.',
                'icon' => 'ph:chat-teardrop-text',
            ],
            'list_document_versions' => [
                'class' => ListDocumentVersions::class,
                'type' => 'read',
                'name' => 'List Document Versions',
                'description' => 'List version history for a document.',
                'icon' => 'ph:clock-counter-clockwise',
            ],
            'restore_document_version' => [
                'class' => RestoreDocumentVersion::class,
                'type' => 'write',
                'name' => 'Restore Document Version',
                'description' => 'Restore a document to a previous version.',
                'icon' => 'ph:clock-counter-clockwise',
            ],
            'list_document_attachments' => [
                'class' => ListDocumentAttachments::class,
                'type' => 'read',
                'name' => 'List Document Attachments',
                'description' => 'List file attachments on a document.',
                'icon' => 'ph:paperclip',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        if ($class === SearchDocuments::class) {
            // SearchDocuments can fall back to keyword search, but the indexing
            // service is still passed so semantic search is available when the
            // workspace has embeddings.
            return new SearchDocuments($agent, $this->permissionService, app(DocumentIndexingService::class));
        }

        // Other document tools only need the acting agent and permission layer;
        // model-level workspace scopes protect the actual records.
        return new $class($agent, $this->permissionService);
    }
}
