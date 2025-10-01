#!/bin/bash

# AppliFlow Scheduler Monitor Script
# This script monitors the scheduler health and can restart the container if needed

CONTAINER_NAME="recruit-me-app"
LOG_FILE="/Users/mattcieslak/test-applyai/storage/logs/scheduler_monitor.log"
EMAIL="mattcieslak93@gmail.com"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

send_alert() {
    local subject="$1"
    local message="$2"
    
    # Simple mail alert (requires mail command or can be enhanced with curl/API)
    echo "$message" | mail -s "$subject" "$EMAIL" 2>/dev/null || {
        log "WARNING: Failed to send email alert"
    }
}

check_container_running() {
    docker ps --filter "name=$CONTAINER_NAME" --format "{{.Names}}" | grep -q "$CONTAINER_NAME"
}

check_scheduler_health() {
    # Run the health monitor command inside the container
    docker exec "$CONTAINER_NAME" php artisan scheduler:monitor --force 2>/dev/null
    return $?
}

restart_container() {
    log "CRITICAL: Restarting container $CONTAINER_NAME"
    
    docker restart "$CONTAINER_NAME" &>/dev/null
    
    if [ $? -eq 0 ]; then
        log "SUCCESS: Container restarted successfully"
        send_alert "🔄 AppliFlow Container Restarted" "Container $CONTAINER_NAME has been automatically restarted due to scheduler issues. Monitoring will continue."
        return 0
    else
        log "ERROR: Failed to restart container"
        send_alert "🚨 AppliFlow Container Restart Failed" "CRITICAL: Failed to restart container $CONTAINER_NAME. Manual intervention required."
        return 1
    fi
}

main() {
    log "Starting scheduler health check..."
    
    # Check if container is running
    if ! check_container_running; then
        log "ERROR: Container $CONTAINER_NAME is not running"
        send_alert "🚨 AppliFlow Container Down" "Container $CONTAINER_NAME is not running. Manual intervention required."
        exit 1
    fi
    
    # Check scheduler health
    if ! check_scheduler_health; then
        log "WARNING: Scheduler health check failed"
        
        # Wait 2 minutes and check again
        log "Waiting 2 minutes before retry..."
        sleep 120
        
        if ! check_scheduler_health; then
            log "CRITICAL: Scheduler health check failed twice - initiating container restart"
            restart_container
        else
            log "SUCCESS: Scheduler recovered on retry"
        fi
    else
        log "SUCCESS: Scheduler health check passed"
    fi
}

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Run the main function
main

log "Health check completed"