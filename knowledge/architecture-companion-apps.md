# Architecture: Component Companion Apps Pattern

## Status: INSIGHT
**Priority**: HIGH  
**Tags**: `architecture`, `companion-apps`, `microservices`, `rest-api`, `components`, `evolution`, `pattern`, `decision`  
**Date**: 2025-08-14  
**Component**: `conduit-core`, `all-components`  

## Discovery Context

Key insight from development session: "Started as CLI microservices but there's a reason we have REST APIs" - REST APIs are essential for OAuth flows (redirect URLs), webhook endpoints, external service integration, browser dashboards, WebSockets, and background jobs.

## Architectural Pattern

THE SHIT components can have companion Laravel apps for web features. Each component operates as a CLI microservice with an optional web companion for browser-based interactions.

### Core Architecture
```
┌─────────────────────────────────────────────────┐
│                  THE SHIT CLI                   │
│                                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐    │
│  │ Spotify  │  │Event Bus │  │Orchestr. │    │
│  │Component │  │Component │  │Component │    │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘    │
│       │             │             │            │
└───────┼─────────────┼─────────────┼────────────┘
        │             │             │
    Event Bus     Event Bus     Event Bus
        │             │             │
┌───────┼─────────────┼─────────────┼────────────┐
│       │             │             │            │
│  ┌────▼─────┐  ┌────▼─────┐  ┌────▼─────┐    │
│  │ Spotify  │  │Event Bus │  │Orchestr. │    │
│  │Companion │  │Companion │  │Companion │    │
│  │   App    │  │   App    │  │   App    │    │
│  └──────────┘  └──────────┘  └──────────┘    │
│                                                 │
│              Web Companion Apps                 │
└─────────────────────────────────────────────────┘
```

## Component Examples

### Spotify Companion
- **OAuth Handshake**: Handles Spotify authentication redirects
- **Activity Logging**: Spatie activity log for music history tracking
- **Dashboards**: Web UI for playlist management and listening analytics
- **Real-time Updates**: WebSocket connections for now-playing status

### Event Bus Companion
- **REST API**: HTTP endpoints for external event submission
- **Webhook Receivers**: Accept events from GitHub, Stripe, etc.
- **Event Stream Visualization**: Real-time event monitoring dashboard
- **Event Replay**: Web UI for debugging and event history

### Orchestrator Companion
- **Agent Dashboard**: Real-time view of all active agents
- **Task Queue Management**: Web interface for priority adjustments
- **Work Distribution API**: REST endpoints for external task submission
- **Performance Metrics**: Agent efficiency and task completion analytics

### GitHub Companion
- **PR Review Dashboard**: Consolidated view of pull requests
- **Webhook Handlers**: Process GitHub events (pushes, PRs, issues)
- **Deployment Pipelines**: CI/CD status and deployment triggers
- **Repository Analytics**: Code velocity and contribution metrics

## Communication Pattern

Components and companions communicate via the event bus:

1. **CLI → Companion**: Component emits events that companion subscribes to
2. **Companion → CLI**: Web app emits events back to CLI for processing
3. **Event-Driven**: All communication is asynchronous via events
4. **Decoupled**: Components work independently, companions are optional

## Implementation Details

### CLI Component Responsibilities
- Core business logic
- Command-line interface
- Local file operations
- Direct API integrations
- Event emission

### Companion App Responsibilities
- Web UI and dashboards
- OAuth redirect handling
- Webhook endpoints
- REST API exposure
- WebSocket connections
- Background job processing
- Browser-based interactions

## Benefits

1. **True Microservices**: Each component is fully autonomous
2. **Optional Enhancement**: Companions add value but aren't required
3. **Separation of Concerns**: CLI for logic, web for browser features
4. **Scalability**: Components and companions can scale independently
5. **Technology Freedom**: Companions could use different frameworks if needed

## Design Rationale

The companion app pattern emerged from recognizing that certain features inherently require web capabilities:

- **OAuth Flows**: Need redirect URLs that browsers can navigate to
- **Webhooks**: External services need HTTP endpoints to call
- **Dashboards**: Visual data needs browser rendering
- **Real-time Updates**: WebSockets provide efficient browser communication
- **Background Jobs**: Long-running tasks need queue workers

Rather than forcing these into the CLI or creating a monolithic web app, the companion pattern provides focused web capabilities for each component while maintaining the core microservice architecture.

## Future Considerations

- Companions could share authentication infrastructure
- Event bus companion becomes the central hub for all inter-component communication
- Companions could be deployed as serverless functions for cost efficiency
- Mobile companions could extend the pattern to native apps

## Related Knowledge
- Component Architecture documentation
- Event Bus Service implementation
- Microservices best practices
- OAuth implementation patterns