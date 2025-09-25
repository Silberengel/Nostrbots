#!/bin/bash

# Nostrbots Docker Entrypoint
# Handles bot execution, scheduling, and multi-bot management

set -e

# Show help information
show_help() {
    echo "Nostrbots Docker Container"
    echo "========================="
    echo ""
    echo "Usage: docker run [OPTIONS] nostrbots [COMMAND] [ARGS]"
    echo ""
    echo "Commands:"
    echo "  help                    Show this help message"
    echo "  list-bots              List all configured bots"
    echo "  run-bot --bot NAME     Run a specific bot"
    echo "  run-all-bots           Run all configured bots"
    echo "  schedule               Run in schedule mode (for cron)"
    echo "  test                   Run test suite"
    echo "  generate-key           Generate new Nostr key"
    echo ""
    echo "Options:"
    echo "  --bot NAME             Specify bot name"
    echo "  --dry-run              Validate without publishing"
    echo "  --verbose              Enable verbose output"
    echo "  --schedule             Run in scheduled mode"
    echo ""
    echo "Examples:"
    echo "  # List all bots"
    echo "  docker run nostrbots list-bots"
    echo ""
    echo "  # Run specific bot"
    echo "  docker run nostrbots run-bot --bot daily-office"
    echo ""
    echo "  # Run all bots (for scheduled execution)"
    echo "  docker run nostrbots run-all-bots"
    echo ""
    echo "  # Test without publishing"
    echo "  docker run nostrbots run-bot --bot daily-office --dry-run --verbose"
}

# List all configured bots
list_bots() {
    echo "🤖 Configured Bots:"
    echo "==================="
    
    if [ ! -d "/app/bots" ] || [ -z "$(ls -A /app/bots)" ]; then
        echo "No bots configured. Create bot directories in /app/bots/"
        echo ""
        echo "Example structure:"
        echo "  /app/bots/daily-office/"
        echo "  ├── config.json"
        echo "  ├── templates/"
        echo "  └── output/"
        return 0
    fi
    
    for bot_dir in /app/bots/*/; do
        if [ -d "$bot_dir" ]; then
            bot_name=$(basename "$bot_dir")
            config_file="$bot_dir/config.json"
            
            if [ -f "$config_file" ]; then
                echo "✅ $bot_name - $(jq -r '.name // "Unnamed Bot"' "$config_file")"
                echo "   Schedule: $(jq -r '.schedule // "Not scheduled"' "$config_file")"
                echo "   Relays: $(jq -r '.relays | length' "$config_file") relay(s)"
            else
                echo "⚠️  $bot_name - Missing config.json"
            fi
            echo ""
        fi
    done
}

# Run a specific bot
run_bot() {
    if [ -z "$BOT_NAME" ]; then
        echo "❌ Error: Bot name required. Use --bot NAME"
        exit 1
    fi
    
    bot_dir="/app/bots/$BOT_NAME"
    config_file="$bot_dir/config.json"
    
    if [ ! -d "$bot_dir" ]; then
        echo "❌ Error: Bot directory not found: $bot_dir"
        exit 1
    fi
    
    if [ ! -f "$config_file" ]; then
        echo "❌ Error: Bot configuration not found: $config_file"
        exit 1
    fi
    
    echo "🤖 Running bot: $BOT_NAME"
    echo "========================"
    
    # Extract bot configuration
    bot_name=$(jq -r '.name // "Unnamed Bot"' "$config_file")
    relays=$(jq -r '.relays[]' "$config_file" | tr '\n' ' ')
    content_kind=$(jq -r '.content_kind // "30023"' "$config_file")
    content_level=$(jq -r '.content_level // 0' "$config_file")
    
    echo "Bot: $bot_name"
    echo "Relays: $relays"
    echo "Content Kind: $content_kind"
    echo "Content Level: $content_level"
    echo ""
    
    # Generate content if generator script exists
    generator_script="$bot_dir/generate-content.php"
    if [ -f "$generator_script" ]; then
        echo "📝 Generating content..."
        php "$generator_script" "$bot_dir"
        
        if [ $? -ne 0 ]; then
            echo "❌ Content generation failed"
            exit 1
        fi
        echo "✅ Content generated"
        echo ""
    fi
    
    # Find the latest generated article
    output_dir="$bot_dir/output"
    if [ -d "$output_dir" ]; then
        latest_article=$(find "$output_dir" -name "*.adoc" -o -name "*.md" | sort | tail -1)
        
        if [ -n "$latest_article" ]; then
            echo "📄 Publishing: $(basename "$latest_article")"
            
            # Build command arguments
            cmd_args="publish \"$latest_article\""
            
            if [ "$content_kind" != "30023" ]; then
                cmd_args="$cmd_args --content-kind $content_kind"
            fi
            
            if [ "$content_level" != "0" ]; then
                cmd_args="$cmd_args --content-level $content_level"
            fi
            
            if [ "$DRY_RUN" = true ]; then
                cmd_args="$cmd_args --dry-run"
            fi
            
            if [ "$VERBOSE" = true ]; then
                cmd_args="$cmd_args --verbose"
            fi
            
            # Execute the publishing command
            echo "🚀 Executing: php nostrbots.php $cmd_args"
            echo ""
            
            cd /app
            eval "php nostrbots.php $cmd_args"
            
            if [ $? -eq 0 ]; then
                echo ""
                echo "✅ Bot '$BOT_NAME' completed successfully"
            else
                echo ""
                echo "❌ Bot '$BOT_NAME' failed"
                exit 1
            fi
        else
            echo "❌ No articles found in output directory"
            exit 1
        fi
    else
        echo "❌ Output directory not found: $output_dir"
        exit 1
    fi
}

# Run all configured bots
run_all_bots() {
    echo "🤖 Running All Bots"
    echo "==================="
    
    if [ ! -d "/app/bots" ] || [ -z "$(ls -A /app/bots)" ]; then
        echo "No bots configured."
        return 0
    fi
    
    success_count=0
    total_count=0
    
    for bot_dir in /app/bots/*/; do
        if [ -d "$bot_dir" ]; then
            bot_name=$(basename "$bot_dir")
            config_file="$bot_dir/config.json"
            
            if [ -f "$config_file" ]; then
                total_count=$((total_count + 1))
                echo ""
                echo "🔄 Processing bot: $bot_name"
                
                # Check if bot should run based on schedule
                if [ "$SCHEDULE_MODE" = true ]; then
                    schedule=$(jq -r '.schedule // null' "$config_file")
                    if [ "$schedule" != "null" ]; then
                        # Check if current time matches schedule
                        current_hour=$(date -u +%H)
                        current_minute=$(date -u +%M)
                        current_time="$current_hour:$current_minute"
                        
                        if [[ "$schedule" == *"$current_time"* ]]; then
                            echo "⏰ Schedule match: $schedule"
                            BOT_NAME="$bot_name" run_bot
                            if [ $? -eq 0 ]; then
                                success_count=$((success_count + 1))
                            fi
                        else
                            echo "⏭️  Skipping (schedule: $schedule, current: $current_time)"
                        fi
                    else
                        echo "⏭️  Skipping (no schedule configured)"
                    fi
                else
                    # Run all bots regardless of schedule
                    BOT_NAME="$bot_name" run_bot
                    if [ $? -eq 0 ]; then
                        success_count=$((success_count + 1))
                    fi
                fi
            fi
        fi
    done
    
    echo ""
    echo "📊 Summary: $success_count/$total_count bots completed successfully"
    
    if [ $success_count -eq $total_count ]; then
        exit 0
    else
        exit 1
    fi
}

# Run test suite
run_tests() {
    echo "🧪 Running Test Suite"
    echo "====================="
    cd /app
    php run-tests.php
}

# Generate new key
generate_key() {
    echo "🔑 Generating New Nostr Key"
    echo "==========================="
    cd /app
    php generate-key.php --export
}

# Default values
BOT_NAME=""
DRY_RUN=false
VERBOSE=false
SCHEDULE_MODE=false

# Main command dispatcher
case "${1:-help}" in
    help|--help|-h)
        show_help
        ;;
    list-bots)
        list_bots
        ;;
    run-bot)
        # Parse arguments for run-bot command
        shift
        while [[ $# -gt 0 ]]; do
            case $1 in
                --bot)
                    BOT_NAME="$2"
                    shift 2
                    ;;
                --dry-run)
                    DRY_RUN=true
                    shift
                    ;;
                --verbose)
                    VERBOSE=true
                    shift
                    ;;
                *)
                    echo "Unknown option for run-bot: $1"
                    show_help
                    exit 1
                    ;;
            esac
        done
        run_bot
        ;;
    run-all-bots)
        run_all_bots
        ;;
    schedule)
        SCHEDULE_MODE=true
        run_all_bots
        ;;
    test)
        run_tests
        ;;
    generate-key)
        generate_key
        ;;
    *)
        echo "Unknown command: $1"
        show_help
        exit 1
        ;;
esac