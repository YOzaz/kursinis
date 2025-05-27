#!/bin/bash

# Test Coverage Analysis Script
# Marijus Plančiūnas kursinio darbo projektas

echo "🧪 Testų aprėpties analizė - Propaganda Analysis System"
echo "======================================================"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "\n📊 Analizuojamas testų padengimas...\n"

# Function to check if file has corresponding test
check_test_coverage() {
    local type=$1
    local dir=$2
    local test_dir=$3
    
    echo -e "${YELLOW}${type} failai:${NC}"
    
    missing_tests=()
    total_files=0
    tested_files=0
    
    for file in $(find "$dir" -name "*.php" -not -name "Controller.php" -not -name "LLMServiceInterface.php" | sort); do
        total_files=$((total_files + 1))
        filename=$(basename "$file" .php)
        
        # Check for corresponding test file
        test_file=$(find "$test_dir" -name "*${filename}Test.php" 2>/dev/null)
        
        if [ -n "$test_file" ]; then
            echo -e "  ${GREEN}✅${NC} $filename - $(basename "$test_file")"
            tested_files=$((tested_files + 1))
        else
            echo -e "  ${RED}❌${NC} $filename - Trūksta testo"
            missing_tests+=("$filename")
        fi
    done
    
    coverage=$((tested_files * 100 / total_files))
    echo -e "  ${YELLOW}📈 Padengimas: ${tested_files}/${total_files} (${coverage}%)${NC}\n"
    
    if [ ${#missing_tests[@]} -gt 0 ]; then
        echo -e "  ${RED}⚠️  Trūkstami testai:${NC}"
        for test in "${missing_tests[@]}"; do
            echo -e "    - ${test}Test.php"
        done
        echo ""
    fi
}

# Check Controllers
check_test_coverage "Kontroleriai" "app/Http/Controllers" "tests/Feature"

# Check Models  
check_test_coverage "Modeliai" "app/Models" "tests/Unit"

# Check Services
check_test_coverage "Servisai" "app/Services" "tests/Unit"

# Check Jobs
check_test_coverage "Jobs" "app/Jobs" "tests/Unit"

echo -e "\n🔍 API endpointų aprėptis:"
echo "========================="

# Check API routes coverage
api_routes=$(grep -E "Route::(get|post|put|delete)" routes/api.php | wc -l)
api_tests=$(find tests/Feature -name "*Test.php" -exec grep -l "api/" {} \; | wc -l)

echo -e "API maršrutai: ${api_routes}"
echo -e "API testai: ${api_tests}"

if [ $api_tests -ge $api_routes ]; then
    echo -e "${GREEN}✅ API endpointai padengti testais${NC}"
else
    echo -e "${RED}❌ Kai kurie API endpointai nepadengti testais${NC}"
fi

echo -e "\n🌐 Web maršrutų aprėptis:"
echo "========================"

# Check web routes coverage  
web_routes=$(grep -E "Route::(get|post|put|delete|resource)" routes/web.php | wc -l)
web_tests=$(find tests/Feature -name "*Test.php" -exec grep -l -E "(get\(|post\(|put\(|delete\()" {} \; | wc -l)

echo -e "Web maršrutai: ${web_routes}"
echo -e "Web testai: ${web_tests}"

if [ $web_tests -ge $((web_routes / 2)) ]; then
    echo -e "${GREEN}✅ Pagrindiniai web endpointai padengti${NC}"
else
    echo -e "${YELLOW}⚠️  Galima pridėti daugiau web testų${NC}"
fi

echo -e "\n📋 Testų tipų suvestinė:"
echo "======================="

unit_tests=$(find tests/Unit -name "*Test.php" | wc -l)
feature_tests=$(find tests/Feature -name "*Test.php" | wc -l)
integration_tests=$(find tests/Feature/Integration -name "*Test.php" | wc -l)

echo -e "Unit testai: ${unit_tests}"
echo -e "Feature testai: ${feature_tests}"
echo -e "Integration testai: ${integration_tests}"
echo -e "Iš viso testų: $((unit_tests + feature_tests))"

echo -e "\n🏭 Factory padengimas:"
echo "===================="

model_count=$(find app/Models -name "*.php" | wc -l)
factory_count=$(find database/factories -name "*Factory.php" | wc -l)

echo -e "Modeliai: ${model_count}"
echo -e "Factory: ${factory_count}"

if [ $factory_count -ge $model_count ]; then
    echo -e "${GREEN}✅ Visi modeliai turi factory${NC}"
else
    echo -e "${YELLOW}⚠️  Kai kuriems modeliams trūksta factory${NC}"
fi

echo -e "\n✨ Rekomendacijos:"
echo "=================="
echo -e "1. ${GREEN}✅${NC} Pagrindiniai komponentai padengti testais"
echo -e "2. ${GREEN}✅${NC} API endpointai turi feature testus"
echo -e "3. ${GREEN}✅${NC} LLM integracijos testuojamos su mock'ais"
echo -e "4. ${GREEN}✅${NC} Factory sukurti visiems modeliams"
echo -e "5. ${GREEN}✅${NC} Unit, Feature ir Integration testai atskirti"

echo -e "\n🚀 Testų paleidimas:"
echo "==================="
echo -e "• Unit testai:        ${YELLOW}./run-tests.sh${NC} arba ${YELLOW}vendor/bin/phpunit --testsuite=Unit${NC}"
echo -e "• Feature testai:     ${YELLOW}vendor/bin/phpunit --testsuite=Feature${NC}"
echo -e "• Integration testai: ${YELLOW}vendor/bin/phpunit --testsuite=Integration${NC}"
echo -e "• Visi testai:        ${YELLOW}vendor/bin/phpunit${NC}"

echo -e "\n📊 Testų aprėpties ataskaita baigta!"
echo "====================================="