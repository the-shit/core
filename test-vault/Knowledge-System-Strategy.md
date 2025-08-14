---
title: Knowledge System Strategy
created: 2025-08-13
tags: [knowledge-management, strategy, architecture, the-shit]
source: architectural-decision
---

# Knowledge System Strategy

## The Problem
Multiple competing knowledge systems:
1. **💩 System** - THE SHIT's native knowledge:capture
2. **Conduit Knowledge** - External knowledge management
3. **MindMap** - Retired Laravel knowledge graph
4. **Obsidian** - Human-readable second brain

## The Solution: Unified Knowledge Bus

### Architecture
```
AI Analysis → Event Emission → Knowledge Capture → Obsidian Notes
     ↓            ↓                ↓                    ↓
   GPT-5      events.jsonl    SQLite + Vectors    Markdown Files
```

### Core Principle: Event-Driven Knowledge Flow

All knowledge operations emit events:
- AI conversations
- Code analysis
- Component interactions
- User decisions
- System insights

### Storage Strategy

#### SQLite for Components
**Why**: 
- Component independence
- No shared dependencies
- Portable (single file)
- Fast queries
- Built-in FTS5

**Use Cases**:
- Local search index
- Vector embeddings
- Relationship graphs
- Quick lookups

#### Obsidian for Humans
**Why**:
- Human-readable markdown
- Visual graph view
- Cross-platform
- Plugin ecosystem
- Git-friendly

**Use Cases**:
- Documentation
- Decision records
- Learning notes
- Architecture diagrams

#### JSONL for Events
**Why**:
- Append-only (fast)
- Streamable
- Human-readable
- Replayable

**Use Cases**:
- Audit trail
- Event sourcing
- Activity logging
- Debugging

## Implementation Patterns

### Pattern 1: AI-Generated Knowledge
```php
// AI analyzes code
$analysis = $grok->analyze($component);

// Generate Obsidian note
$note = $obsidian->generateNote($analysis);

// Create embeddings
$embedding = $gpt5->embed($note->content);

// Emit event
EventBus::emit('knowledge.captured', $note);
```

### Pattern 2: Knowledge Graph Building
```php
// Extract relationships
$links = $obsidian->extractWikilinks($content);

// Build graph
$graph = new KnowledgeGraph($links);

// Find clusters
$topics = $graph->detectCommunities();
```

### Pattern 3: Semantic Search
```php
// User query
$query = "architectural patterns for testing";

// Generate embedding
$queryVector = $gpt5->embed($query);

// Find similar notes
$results = $obsidian->semanticSearch($queryVector);
```

## MindMap Integration Strategy

The MindMap project's concepts should be integrated:

### What to Keep
- Entity-relationship model
- Type validation
- Metadata support
- Graph traversal algorithms
- Observation tracking

### How to Integrate
1. Create `knowledge-graph` component
2. Use MindMap's schema design
3. Emit events for all operations
4. Sync with Obsidian vault

## Knowledge Capture Automation

### Automatic Capture Points
1. **AI Conversations**: Every ask/analyze command
2. **Component Events**: Installation, delegation, execution
3. **Error Resolution**: Bugs fixed, patterns discovered
4. **Architecture Decisions**: Design choices, trade-offs
5. **Performance Insights**: Optimization discoveries

### Daily Knowledge Summary
```bash
php 💩 obsidian:daily
```
Creates note with:
- AI conversations of the day
- Component events
- Code changes analyzed
- Decisions made
- Insights captured

## Benefits of This Approach

1. **No Knowledge Loss**: Everything is captured
2. **Multiple Access Patterns**: SQL, text, semantic
3. **Human & AI Friendly**: Markdown for humans, vectors for AI
4. **Component Independence**: Each component owns its knowledge
5. **Event Sourcing**: Complete history, replayable

## Migration Path

### Phase 1: Current State
- Obsidian-Bridge component ✅
- Basic sync and search ✅
- Event emission ✅

### Phase 2: Knowledge Capture
- Auto-generate notes from AI
- Capture component events
- Build daily summaries

### Phase 3: Graph Integration
- Port MindMap concepts
- Build relationship graphs
- Enable graph queries

### Phase 4: Full Automation
- Watch mode for real-time
- Automatic categorization
- Smart recommendations

## Related Notes
- [[Obsidian-Bridge-Implementation]]
- [[MindMap-Architecture-Review]]
- [[Component-Liberation-Philosophy]]
- [[Event-Driven-Architecture]]

## Decision Record

**Decision**: Use Obsidian as primary knowledge store with SQLite for indexing
**Date**: 2025-08-13
**Rationale**: Combines human readability with machine queryability
**Alternatives Considered**: 
- PostgreSQL (too heavy for components)
- Neo4j (too complex)
- Pure JSONL (not queryable enough)

---
*Strategic decision during THE SHIT knowledge system design*