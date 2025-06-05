#!/bin/bash

# Analysis Database Cleaning Script
# Convenient wrapper for the Laravel Artisan analysis:clean command
# 
# Author: Marijus Plančiūnas
# Course: Kursinis darbas, VU MIF

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🧹 Propaganda Analysis System - Database Cleaner${NC}"
echo -e "${BLUE}=================================================${NC}"
echo

# Function to show help
show_help() {
    echo -e "${YELLOW}Available cleaning options:${NC}"
    echo
    echo -e "${GREEN}1. Full Clean${NC} - Remove ALL analysis data (keeps expert annotations)"
    echo "   Usage: $0 full"
    echo "   ⚠️  This removes all LLM results but preserves expert annotations"
    echo
    echo -e "${GREEN}2. Complete Wipe${NC} - Remove EVERYTHING including expert annotations"
    echo "   Usage: $0 wipe"
    echo "   ⚠️  This removes ALL data - use only for complete fresh start"
    echo
    echo -e "${GREEN}3. Queue Only${NC} - Clean only queue jobs, keep analysis data"
    echo "   Usage: $0 queue"
    echo "   ✅ Safe - only removes pending/failed jobs"
    echo
    echo -e "${GREEN}4. Old Data${NC} - Clean data older than X days"
    echo "   Usage: $0 old [days]"
    echo "   Example: $0 old 30  # Clean data older than 30 days"
    echo
    echo -e "${GREEN}5. Status${NC} - Show current database status without cleaning"
    echo "   Usage: $0 status"
    echo
    echo -e "${YELLOW}All operations include confirmation prompts unless run with --force${NC}"
    echo
}

# Function to check if we're in the right directory
check_environment() {
    if [[ ! -f "artisan" ]]; then
        echo -e "${RED}❌ Error: Not in Laravel project directory${NC}"
        echo "Please run this script from the project root directory."
        exit 1
    fi
    
    if [[ ! -f ".env" ]]; then
        echo -e "${YELLOW}⚠️  Warning: .env file not found${NC}"
        echo "Make sure you're in the correct project directory."
    fi
}

# Function to run artisan command with error handling
run_artisan() {
    local cmd="$1"
    echo -e "${BLUE}Running: php artisan $cmd${NC}"
    echo
    
    if ! php artisan $cmd; then
        echo -e "${RED}❌ Command failed${NC}"
        exit 1
    fi
}

# Main logic
check_environment

case "${1:-}" in
    "full"|"keep-expert")
        echo -e "${YELLOW}🔒 Full Clean - Keeping expert annotations, removing LLM results${NC}"
        run_artisan "analysis:clean --keep-expert"
        ;;
    "wipe"|"complete")
        echo -e "${RED}💀 Complete Wipe - Removing ALL analysis data${NC}"
        echo -e "${RED}This will delete everything including expert annotations!${NC}"
        run_artisan "analysis:clean"
        ;;
    "queue"|"jobs")
        echo -e "${GREEN}🚀 Queue Clean - Removing only queue jobs${NC}"
        run_artisan "analysis:clean --jobs-only"
        ;;
    "old")
        days="${2:-7}"
        echo -e "${BLUE}📅 Old Data Clean - Removing data older than $days days${NC}"
        run_artisan "analysis:clean --older-than=$days"
        ;;
    "status"|"stats"|"info")
        echo -e "${BLUE}📊 Database Status${NC}"
        # Use a dummy command that will show stats but cancel
        echo "n" | php artisan analysis:clean --keep-expert 2>/dev/null || true
        ;;
    "help"|"--help"|"-h"|"")
        show_help
        ;;
    "force-full")
        echo -e "${YELLOW}🔒 Force Full Clean - No confirmation${NC}"
        run_artisan "analysis:clean --keep-expert --force"
        ;;
    "force-wipe")
        echo -e "${RED}💀 Force Complete Wipe - No confirmation${NC}"
        run_artisan "analysis:clean --force"
        ;;
    "force-queue")
        echo -e "${GREEN}🚀 Force Queue Clean - No confirmation${NC}"
        run_artisan "analysis:clean --jobs-only --force"
        ;;
    *)
        echo -e "${RED}❌ Unknown option: $1${NC}"
        echo
        show_help
        exit 1
        ;;
esac

echo
echo -e "${GREEN}✅ Operation completed successfully!${NC}"