#!/bin/bash

# Docker Stack Management Script for Nostrbots
# Provides easy commands to manage the Docker Stack deployment

set -euo pipefail

# Configuration
STACK_NAME="nostrbots"
COMPOSE_FILE_ELK="docker-compose.stack.yml"
COMPOSE_FILE_BASIC="docker-compose.basic.yml"
PROJECT_DIR="/opt/nostrbots"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Show help
show_help() {
    echo "Nostrbots Docker Stack Management"
    echo "================================="
    echo ""
    echo "Usage: $0 COMMAND [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  deploy [elk|basic]    Deploy the stack (elk=with Elasticsearch, basic=without)"
    echo "  stop                  Stop the stack"
    echo "  restart [elk|basic]   Restart the stack"
    echo "  status                Show stack status"
    echo "  logs [service]        Show logs for a service"
    echo "  ps                    Show running services"
    echo "  cleanup               Remove stack and clean up"
    echo "  health                Check health of all services"
    echo "  update [elk|basic]    Update and redeploy stack"
    echo ""
    echo "Examples:"
    echo "  $0 deploy elk         # Deploy with Elasticsearch"
    echo "  $0 deploy basic       # Deploy basic version"
    echo "  $0 logs event-indexer # Show event-indexer logs"
    echo "  $0 status             # Show stack status"
    echo "  $0 health             # Check all services health"
    echo ""
    echo "Services (for logs command):"
    echo "  - orly-relay"
    echo "  - jenkins"
    echo "  - elasticsearch"
    echo "  - kibana"
    echo "  - logstash"
    echo "  - event-indexer"
    echo "  - backup-agent"
    echo "  - nostrbots-agent"
    echo ""
}

# Check if Docker Swarm is initialized
check_swarm() {
    if ! docker info --format '{{.Swarm.LocalNodeState}}' | grep -q "active"; then
        log_error "Docker Swarm is not initialized!"
        log_info "Run: docker swarm init"
        exit 1
    fi
}

# Check if secrets exist
check_secrets() {
    local missing_secrets=()
    
    if ! docker secret ls --format "{{.Name}}" | grep -q "nostr_bot_key_encrypted"; then
        missing_secrets+=("nostr_bot_key_encrypted")
    fi
    
    if ! docker secret ls --format "{{.Name}}" | grep -q "nostr_bot_npub"; then
        missing_secrets+=("nostr_bot_npub")
    fi
    
    if [[ ${#missing_secrets[@]} -gt 0 ]]; then
        log_error "Missing Docker secrets: ${missing_secrets[*]}"
        log_info "Run the setup script first: sudo ./setup-production-with-elasticsearch.sh"
        exit 1
    fi
}

# Deploy stack
deploy_stack() {
    local compose_type="${1:-elk}"
    local compose_file
    
    case "$compose_type" in
        "elk")
            compose_file="$COMPOSE_FILE_ELK"
            ;;
        "basic")
            compose_file="$COMPOSE_FILE_BASIC"
            ;;
        *)
            log_error "Invalid compose type: $compose_type"
            log_info "Use 'elk' or 'basic'"
            exit 1
            ;;
    esac
    
    if [[ ! -f "$compose_file" ]]; then
        log_error "Compose file not found: $compose_file"
        exit 1
    fi
    
    log_info "Deploying $STACK_NAME stack with $compose_type configuration..."
    
    # Stop existing stack if running
    if docker stack ls --format "{{.Name}}" | grep -q "$STACK_NAME"; then
        log_info "Stopping existing stack..."
        docker stack rm "$STACK_NAME"
        sleep 10
    fi
    
    # Deploy new stack
    cd "$PROJECT_DIR"
    local compose_path
    if [[ "$compose_file" == "docker-compose.stack.yml" ]]; then
        compose_path="/home/madmin/Projects/GitCitadel/Nostrbots/docker-compose.stack.yml"
    elif [[ "$compose_file" == "docker-compose.basic.yml" ]]; then
        compose_path="/home/madmin/Projects/GitCitadel/Nostrbots/docker-compose.basic.yml"
    else
        compose_path="$compose_file"
    fi
    
    if docker stack deploy -c "$compose_path" "$STACK_NAME"; then
        log_success "Stack deployed successfully"
        
        # Wait for services to start
        log_info "Waiting for services to start..."
        sleep 30
        
        # Show status
        show_status
    else
        log_error "Failed to deploy stack"
        exit 1
    fi
}

# Stop stack
stop_stack() {
    log_info "Stopping $STACK_NAME stack..."
    
    if docker stack ls --format "{{.Name}}" | grep -q "$STACK_NAME"; then
        docker stack rm "$STACK_NAME"
        log_success "Stack stopped"
    else
        log_warn "Stack is not running"
    fi
}

# Show stack status
show_status() {
    log_info "Stack Status:"
    echo ""
    
    if docker stack ls --format "{{.Name}}" | grep -q "$STACK_NAME"; then
        docker stack services "$STACK_NAME" --format "table {{.Name}}\t{{.Mode}}\t{{.Replicas}}\t{{.Image}}"
        echo ""
        log_info "Service Details:"
        docker service ls --filter "name=${STACK_NAME}_" --format "table {{.Name}}\t{{.Replicas}}\t{{.Image}}\t{{.Ports}}"
    else
        log_warn "Stack is not running"
    fi
}

# Show service logs
show_logs() {
    local service_name="$1"
    local full_service_name="${STACK_NAME}_${service_name}"
    
    if docker service ls --format "{{.Name}}" | grep -q "$full_service_name"; then
        log_info "Showing logs for $service_name..."
        docker service logs --follow --tail 100 "$full_service_name"
    else
        log_error "Service $service_name not found"
        log_info "Available services:"
        docker service ls --filter "name=${STACK_NAME}_" --format "{{.Name}}" | sed "s/${STACK_NAME}_//"
    fi
}

# Show running services
show_ps() {
    log_info "Running Services:"
    docker service ls --filter "name=${STACK_NAME}_" --format "table {{.Name}}\t{{.Replicas}}\t{{.Image}}\t{{.Ports}}"
}

# Cleanup stack
cleanup_stack() {
    log_warn "This will remove the entire stack and all data!"
    echo -n "Are you sure? (y/N): "
    read -r REPLY
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log_info "Cleaning up $STACK_NAME stack..."
        
        # Stop stack
        stop_stack
        
        # Remove secrets
        log_info "Removing Docker secrets..."
        docker secret rm nostr_bot_key_encrypted >/dev/null 2>&1 || true
        docker secret rm nostr_bot_npub >/dev/null 2>&1 || true
        
        # Remove volumes (optional)
        echo -n "Remove volumes (data will be lost)? (y/N): "
        read -r REPLY
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            log_info "Removing volumes..."
            docker volume ls --filter "name=${STACK_NAME}_" --format "{{.Name}}" | xargs -r docker volume rm
        fi
        
        log_success "Cleanup completed"
    else
        log_info "Cleanup cancelled"
    fi
}

# Check health of services
check_health() {
    log_info "Checking service health..."
    echo ""
    
    # Check Elasticsearch
    if docker service ls --format "{{.Name}}" | grep -q "${STACK_NAME}_elasticsearch"; then
        log_info "Elasticsearch:"
        if curl -s http://localhost:9200/_cluster/health >/dev/null 2>&1; then
            log_success "  ✓ Elasticsearch is healthy"
        else
            log_error "  ✗ Elasticsearch is not responding"
        fi
    fi
    
    # Check Kibana
    if docker service ls --format "{{.Name}}" | grep -q "${STACK_NAME}_kibana"; then
        log_info "Kibana:"
        if curl -s http://localhost:5601/api/status >/dev/null 2>&1; then
            log_success "  ✓ Kibana is healthy"
        else
            log_error "  ✗ Kibana is not responding"
        fi
    fi
    
    # Check Jenkins
    if docker service ls --format "{{.Name}}" | grep -q "${STACK_NAME}_jenkins"; then
        log_info "Jenkins:"
        if curl -s http://localhost:8080/login >/dev/null 2>&1; then
            log_success "  ✓ Jenkins is healthy"
        else
            log_error "  ✗ Jenkins is not responding"
        fi
    fi
    
    # Check Orly Relay
    if docker service ls --format "{{.Name}}" | grep -q "${STACK_NAME}_orly-relay"; then
        log_info "Orly Relay:"
        if curl -s http://localhost:3334/health >/dev/null 2>&1; then
            log_success "  ✓ Orly Relay is healthy"
        else
            log_error "  ✗ Orly Relay is not responding"
        fi
    fi
    
    echo ""
    log_info "Service Status Summary:"
    docker service ls --filter "name=${STACK_NAME}_" --format "table {{.Name}}\t{{.Replicas}}\t{{.Image}}"
}

# Update stack
update_stack() {
    local compose_type="${1:-elk}"
    
    log_info "Updating $STACK_NAME stack..."
    
    # Pull latest images
    log_info "Pulling latest images..."
    docker service ls --filter "name=${STACK_NAME}_" --format "{{.Image}}" | sort -u | xargs -r docker pull
    
    # Redeploy
    deploy_stack "$compose_type"
}

# Main function
main() {
    local command="${1:-help}"
    
    case "$command" in
        "deploy")
            check_swarm
            check_secrets
            deploy_stack "${2:-elk}"
            ;;
        "stop")
            stop_stack
            ;;
        "restart")
            check_swarm
            check_secrets
            deploy_stack "${2:-elk}"
            ;;
        "status")
            show_status
            ;;
        "logs")
            if [[ -z "${2:-}" ]]; then
                log_error "Please specify a service name"
                log_info "Available services:"
                docker service ls --filter "name=${STACK_NAME}_" --format "{{.Name}}" | sed "s/${STACK_NAME}_//" 2>/dev/null || true
                exit 1
            fi
            show_logs "$2"
            ;;
        "ps")
            show_ps
            ;;
        "cleanup")
            cleanup_stack
            ;;
        "health")
            check_health
            ;;
        "update")
            check_swarm
            check_secrets
            update_stack "${2:-elk}"
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            log_error "Unknown command: $command"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
