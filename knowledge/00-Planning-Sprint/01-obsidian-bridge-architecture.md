# Obsidian-Bridge Component Architecture

## Overview
The Obsidian-Bridge component creates a seamless integration between THE SHIT framework and Obsidian knowledge management, enabling AI-powered knowledge generation, semantic search, and bi-directional synchronization.

## Core Concept
```
Obsidian Vault (Human Knowledge) ←→ THE SHIT (AI Processing) ←→ SQLite (Fast Queries)
```

## Architecture Layers

### 1. Storage Layer
- **Obsidian Vault**: Markdown files with frontmatter, tags, and [[wikilinks]]
- **SQLite Database**: Indexed content with vector embeddings for semantic search
- **JSONL Event Stream**: Real-time change tracking and event sourcing

### 2. Processing Layer
- **Markdown Parser**: Extracts content, frontmatter, links, and tags
- **Embedding Generator**: Creates vector representations using AI models
- **Graph Builder**: Constructs knowledge graph from note relationships

### 3. Command Layer
Commands following THE SHIT's simple pattern:
- `obsidian:sync` - Bi-directional synchronization
- `obsidian:search` - Semantic search across vault
- `obsidian:generate` - AI-powered note generation
- `obsidian:watch` - Real-time change monitoring
- `obsidian:graph` - Knowledge graph visualization

## Database Schema

```sql
-- Core notes table with vector embeddings
CREATE TABLE notes (
    id TEXT PRIMARY KEY,
    path TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    content TEXT,
    frontmatter JSON,
    embedding BLOB,
    checksum TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    synced_at DATETIME
);

-- Full-text search index
CREATE VIRTUAL TABLE notes_fts USING fts5(
    title, content, 
    content=notes,
    content_rowid=rowid
);

-- Note relationships from [[wikilinks]]
CREATE TABLE links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_note_id TEXT NOT NULL,
    target_note_id TEXT NOT NULL,
    link_text TEXT,
    context TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(source_note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY(target_note_id) REFERENCES notes(id) ON DELETE CASCADE,
    UNIQUE(source_note_id, target_note_id, link_text)
);

-- Tags from #hashtags and frontmatter
CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    note_id TEXT NOT NULL,
    tag TEXT NOT NULL,
    source TEXT CHECK(source IN ('content', 'frontmatter')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(note_id) REFERENCES notes(id) ON DELETE CASCADE,
    UNIQUE(note_id, tag)
);

-- Sync status tracking
CREATE TABLE sync_status (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    operation TEXT NOT NULL,
    status TEXT CHECK(status IN ('pending', 'in_progress', 'completed', 'failed')),
    details JSON,
    started_at DATETIME,
    completed_at DATETIME,
    error_message TEXT
);

-- Indexes for performance
CREATE INDEX idx_notes_updated ON notes(updated_at);
CREATE INDEX idx_notes_path ON notes(path);
CREATE INDEX idx_links_source ON links(source_note_id);
CREATE INDEX idx_links_target ON links(target_note_id);
CREATE INDEX idx_tags_note ON tags(note_id);
CREATE INDEX idx_tags_tag ON tags(tag);
```

## Integration Points

### 1. AI Component Integration
```php
// Generate knowledge from AI analysis
$analysis = $aiService->analyze($code);
$note = $obsidianBridge->generateNote([
    'title' => "Analysis: {$component}",
    'content' => $analysis,
    'tags' => ['ai-generated', 'code-analysis'],
    'links' => $extractedConcepts
]);
```

### 2. Event System Integration
```php
// Listen for component events
EventBusService::on('ai.conversation', function($event) {
    $obsidianBridge->captureConversation($event);
});

// Emit events for Obsidian changes
EventBusService::emit('obsidian.note.created', [
    'path' => $notePath,
    'title' => $noteTitle
]);
```

### 3. Knowledge Graph Integration
```php
// Build knowledge graph from vault
$graph = $obsidianBridge->buildGraph();
$recommendations = $graph->findRelated($currentNote);
$clusters = $graph->detectCommunities();
```

## File Structure

```
💩-components/obsidian-bridge/
├── app/
│   ├── Commands/
│   │   ├── SyncCommand.php         # Bi-directional sync
│   │   ├── SearchCommand.php       # Semantic search
│   │   ├── GenerateCommand.php     # AI note generation
│   │   ├── WatchCommand.php        # Real-time monitoring
│   │   ├── GraphCommand.php        # Graph visualization
│   │   └── ServeCommand.php        # REST API server
│   ├── Services/
│   │   ├── ObsidianService.php     # Core Obsidian operations
│   │   ├── MarkdownParser.php      # Parse markdown files
│   │   ├── EmbeddingService.php    # Vector embeddings
│   │   ├── GraphBuilder.php        # Knowledge graph
│   │   └── SyncService.php         # Sync orchestration
│   └── Models/
│       ├── Note.php                 # Note entity
│       ├── Link.php                 # Link relationship
│       └── Tag.php                  # Tag entity
├── config/
│   └── obsidian.php                # Configuration
├── database/
│   └── migrations/                 # SQLite migrations
├── storage/
│   ├── vault.db                    # Main database
│   ├── embeddings.db               # Vector index
│   └── sync.jsonl                  # Sync event log
└── tests/
    └── Feature/                     # Feature tests