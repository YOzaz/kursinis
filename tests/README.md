# 🧪 Laravel Propaganda Analysis System - Test Suite

Šis dokumentas aprašo išsamų testų rinkinį Laravel propagandos analizės sistemai.

## 👨‍🎓 Projekto autorystė

**Autorius:** Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)  
**Dėstytojas:** Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)  
**Projekto tipas:** VU MIF Informatikos 3 kurso kursinio darbo dalis

## 📚 Duomenų šaltiniai ir metodologija

Sistema naudoja:
- **ATSPARA projekto korpuso duomenis**: https://www.atspara.mif.vu.lt/ (duomenų šaltinis)
- **Pauliaus Zarankos klasifikavimo metodologiją**: "Propagandos technikų fragmentų identifikavimas lietuviškame tekste"

## 📋 Testų struktūra

### 🏗️ Testų tipai

1. **Unit testai** (23 testai) - Modelių, servisų ir jobs testavimas
2. **Feature testai** (10 testai) - API endpoint'ų, kontrolerių ir UI testai  
3. **Integration testai** (1 testas) - LLM servisų integracijos testai
4. **Browser testai** (2 testai) - UI workflow ir text highlighting testai

### 📁 Direktorijų struktūra

```
tests/
├── Unit/                                
│   ├── Jobs/
│   │   ├── AnalyzeTextJobTest.php       # Teksto analizės job testai (legacy)
│   │   ├── AnalyzeTextJobNewTest.php    # Naujo teksto analizės job testai
│   │   └── BatchAnalysisJobTest.php     # Batch analizės job testai
│   ├── Models/
│   │   ├── AnalysisJobTest.php          # AnalysisJob modelio testai
│   │   ├── ComparisonMetricTest.php     # ComparisonMetric modelio testai
│   │   └── TextAnalysisTest.php         # TextAnalysis modelio testai
│   ├── Services/
│   │   ├── ClaudeServiceTest.php        # Claude API service testai (legacy)
│   │   ├── ClaudeServiceNewTest.php     # Naujo Claude service testai
│   │   ├── GeminiServiceTest.php        # Gemini API service testai (legacy)
│   │   ├── GeminiServiceNewTest.php     # Naujo Gemini service testai
│   │   ├── OpenAIServiceTest.php        # OpenAI API service testai (legacy)
│   │   ├── OpenAIServiceNewTest.php     # Naujo OpenAI service testai
│   │   ├── AbstractLLMServiceTest.php   # Abstraktaus LLM service testai
│   │   ├── ExportServiceTest.php        # CSV/JSON eksporto testai
│   │   ├── MetricsServiceTest.php       # Metrikų skaičiavimo testai
│   │   ├── MetricsServiceStatisticsTest.php # Statistikos skaičiavimo testai
│   │   ├── PromptBuilderServiceTest.php # RISEN prompt kūrimo testai
│   │   ├── PromptServiceTest.php        # Prompt service testai
│   │   ├── StatisticsServiceTest.php    # Statistikos agregavimo testai
│   │   └── TextHighlightingServiceTest.php # Teksto žymėjimo testai
│   ├── Controllers/
│   │   └── AnalysisControllerShowTest.php # AnalysisController show metodo testai
│   └── AnalysisRepeatTest.php           # Analizės pakartojimo testai
├── Feature/
│   ├── AnalysisControllerTest.php       # API analizės endpoint testai
│   ├── AnalysesControllerTest.php       # Analizių sąrašo testai
│   ├── DashboardControllerTest.php      # Dashboard funkcionalumo testai
│   ├── HelpControllerTest.php           # Pagalbos puslapio testai
│   ├── SettingsControllerTest.php       # Nustatymų puslapio testai
│   ├── WebControllerTest.php            # Upload ir progress testai
│   ├── DefaultPromptApiTest.php         # Standartinio prompt API testai
│   ├── TextHighlightingTest.php         # Teksto žymėjimo feature testai
│   ├── Browser/
│   │   ├── TextHighlightingBrowserTest.php    # Browser testai teksto žymėjimui
│   │   └── SimpleTextHighlightingTest.php     # Paprastesni UI testai
│   └── Integration/
│       └── LLMServicesIntegrationTest.php # LLM API integracijos testai
└── TestCase.php                         # Bazinis test klasė su helper metodais

database/factories/
├── AnalysisJobFactory.php               # Analizės darbų test duomenys
├── ComparisonMetricFactory.php          # Metrikų test duomenys
└── TextAnalysisFactory.php              # Tekstų analizės test duomenys
```

## 🎯 Test Coverage

Testų aprėpties statistika (nuo 2025-05-28):

- **Kontroleriai**: 5/5 (100%) ✅
- **Modeliai**: 3/3 (100%) ✅ 
- **Servisai**: 12/12 (100%) ✅
- **Jobs**: 3/3 (100%) ✅
- **Factory**: 3/3 (100%) ✅
- **Naujos funkcijos**: Teksto žymėjimas (100%) ✅

### Testų aprėpties analizė

Naudokite `./check-test-coverage.sh` skriptą, kad gautumėte detalų testų aprėpties raportą.

### Unit testai (models & services)
- ✅ AnalysisJob, ComparisonMetric, TextAnalysis model testai
- ✅ LLM servisų testai (Claude, Gemini, OpenAI - old & new versions)
- ✅ RISEN prompt building service
- ✅ Statistics calculation service
- ✅ Export service testai
- ✅ Text highlighting legend creation testai
- ✅ Factory states ir data generation
- ✅ Jobs testai (AnalyzeTextJob, BatchAnalysisJob)

### Feature testai (controllers & API)
- ✅ Analysis CRUD operations ir API endpoints
- ✅ Dashboard statistics display
- ✅ Help ir Settings puslapių testai
- ✅ Export functionality (CSV, JSON)
- ✅ Default prompt API testai
- ✅ Text highlighting API endpoint testai
- ✅ Form validation ir error handling
- ✅ Web upload ir progress testai

### Browser testai (UI workflows)
- ✅ Text highlighting interface testai
- ✅ AI vs Expert view switching
- ✅ Modal interactions ir accessibility
- ✅ Responsive design elements
- ✅ JavaScript components loading
- ✅ Legend ir color coding testai

### Integration testai (external services)
- ✅ Claude API integration
- ✅ Gemini API integration  
- ✅ OpenAI API integration
- ✅ Custom prompt usage
- ✅ Error handling ir retry logic
- ✅ Rate limiting scenarios

## 🚀 Testų paleidimas

### Visi testai vienu metu
```bash
./run-tests.sh
```

### Atskirų tipų testai
```bash
# Unit testai
php artisan test --testsuite=Unit

# Feature testai
php artisan test --testsuite=Feature

# Integration testai
php artisan test --testsuite=Integration

# Su coverage
php artisan test --coverage
```

### Specifiniai testai
```bash
# Konkretus testas
php artisan test tests/Unit/Models/ExperimentTest.php

# Su debugging
php artisan test --debug tests/Feature/ExperimentControllerTest.php

# Filtruojant testus
php artisan test --filter="test_creates_experiment"
```

## 🛠️ Test Environment Setup

### PHPUnit konfigūracija
Testai naudoja `phpunit.xml` su:
- SQLite in-memory database
- HTTP mocking
- Array cache driver
- Sync queue connection
- Test API keys

### Test duomenų fabrikų naudojimas
```php
// Experiment su rezultatais
$experiment = Experiment::factory()
    ->completed()
    ->create();

ExperimentResult::factory()
    ->count(3)
    ->forExperiment($experiment)
    ->claude()
    ->highAccuracy()
    ->create();

// Analysis job su custom duomenimis
$job = AnalysisJob::factory()
    ->completed()
    ->withTexts(50)
    ->create();
```

### HTTP mocking LLM servisams
```php
// Automatinis mocking visuose testuose
Http::fake();

// Custom response
Http::fake([
    'api.anthropic.com/*' => Http::response([
        'content' => [['text' => '{"primaryChoice": {"choices": ["yes"]}}']]
    ], 200)
]);

// Test helper naudojimas
$this->mockLLMResponse('claude', $customResponse);
$this->mockLLMResponse('all'); // Visiems servisams
```

## 📊 Test Helpers

### TestCase klasės metodai
```php
// LLM response mocking
$this->mockLLMResponse('claude', $responseData);

// Database assertions
$this->assertDatabaseCount('experiments', 5);

// File creation testing
$csvFile = $this->createTestFile($this->createTestCsvContent(3));

// Configuration testing
$this->withConfig(['app.env' => 'test'], function() {
    // test logic
});

// Structure validation
$this->assertValidRisenConfig($experiment->risen_config);
$this->assertValidStatisticsStructure($statistics);
```

## 🔍 Test Scenarios

### Critical User Journeys
1. **Experiment Creation Flow**
   - Create → Edit → Run Analysis → Export
   
2. **Dashboard Analytics**
   - View statistics → Compare experiments → Export data

3. **Error Handling**
   - Invalid form data → API failures → File upload errors

### Edge Cases
- Empty database scenarios
- Invalid JSON responses iš LLM
- Network timeouts ir retries
- File permission issues
- Large datasets

## 📈 Coverage Metrics

Siekiame:
- **90%+ code coverage** overall
- **100% coverage** critical business logic
- **Zero** uncaught exceptions
- **All** user workflows tested

### Coverage Reports
```bash
# HTML report
php artisan test --coverage-html tests/coverage/html

# Text summary  
php artisan test --coverage-text

# Clover XML (CI/CD)
php artisan test --coverage-clover tests/coverage/clover.xml
```

## 🎭 Mock Data Patterns

### Experiment Test Data
```php
// Draft experiment
$draft = Experiment::factory()->draft()->create();

// Running experiment su progress
$running = Experiment::factory()->running()->create();
AnalysisJob::factory()->processing()->forExperiment($running)->create();

// Completed experiment su results
$completed = Experiment::factory()->completed()->create();
ExperimentResult::factory()->count(10)->forExperiment($completed)->create();
```

### LLM Response Patterns
```php
// Positive propaganda detection
$positiveResponse = [
    'primaryChoice' => ['choices' => ['yes']],
    'annotations' => [/* annotations array */],
    'desinformationTechnique' => ['choices' => ['emotional_appeal']]
];

// Negative detection
$negativeResponse = [
    'primaryChoice' => ['choices' => ['no']],
    'annotations' => [],
    'desinformationTechnique' => ['choices' => []]
];
```

## 🚨 Continuous Integration

### GitHub Actions integravimas
```yaml
- name: Run tests
  run: |
    ./run-tests.sh
    
- name: Upload coverage
  uses: codecov/codecov-action@v3
  with:
    file: tests/coverage/clover.xml
```

### Pre-commit hooks
```bash
# Install pre-commit hook
cp hooks/pre-commit .git/hooks/
chmod +x .git/hooks/pre-commit
```

## 📝 Test Writing Guidelines

### Best Practices
1. **Descriptive test names** - `test_user_can_create_experiment_with_valid_data`
2. **Arrange-Act-Assert** pattern
3. **Single concern** per test
4. **Data independence** - each test creates own data
5. **Cleanup after** - use RefreshDatabase trait

### Naming Conventions
```php
// Feature tests
public function test_user_can_[action](): void

// Unit tests  
public function test_[method_name]_[expected_behavior](): void

// Error scenarios
public function test_[action]_throws_exception_when_[condition](): void
```

## 🏆 Quality Gates

### Prieš production deployment
- [ ] Visi testai passing
- [ ] Coverage > 90%
- [ ] Zero critical security issues
- [ ] Performance tests ok
- [ ] Manual testing completed

### Test Maintenance
- Reguliariai atnaujinti test data
- Pridėti testus naujiems features
- Refactorint testus kartu su kodu
- Monitoring test execution times

---

**💡 Tip:** Naudokite `./run-tests.sh` kasdieniam development workflow - jis paleidžia visus testus ir generuoja ataskaitas.

**🔧 Development:** Pridedant naują funkcionalumą, visada rašykite testus pirma (TDD approach).

**📚 Documentation:** Atnaujinkite šį README pridėdami naujus test scenarios.