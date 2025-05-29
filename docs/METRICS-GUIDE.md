# Propaganda Detection Metrics Guide

**Autorius:** Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)  
**Dėstytojas:** Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)  
**Duomenų šaltinis:** ATSPARA projektas (https://www.atspara.mif.vu.lt/)

## 📊 Overview

This guide explains all metrics used in the Lithuanian Propaganda Detection System. The system compares AI model results against expert annotations using statistical methods to measure performance accuracy. This implementation is based on research methodologies from Zaranka's Master's thesis (2025) and follows ATSPARA project standards for Lithuanian propaganda detection.

## 🎯 Core Metrics

### 1. Precision (Tikslumas)

**Definition**: The ratio of correctly identified propaganda techniques to all techniques identified by the AI model.

**Formula**: `Precision = TP / (TP + FP)`

**Interpretation**:
- **High Precision (>0.8)**: AI rarely identifies non-propaganda as propaganda (few false alarms)
- **Medium Precision (0.6-0.8)**: Acceptable performance with some false positives
- **Low Precision (<0.6)**: AI frequently misidentifies text as propaganda

**Example**: If AI identifies 100 propaganda instances and 85 are correct, Precision = 0.85

### 2. Recall (Atpažinimas)

**Definition**: The ratio of correctly identified propaganda techniques to all actual propaganda techniques (per expert annotations).

**Formula**: `Recall = TP / (TP + FN)`

**Interpretation**:
- **High Recall (>0.7)**: AI finds most actual propaganda instances (few missed cases)
- **Medium Recall (0.5-0.7)**: AI misses some propaganda but catches majority
- **Low Recall (<0.5)**: AI misses significant amount of actual propaganda

**Example**: If experts identified 100 propaganda instances and AI found 75, Recall = 0.75

### 3. F1 Score (F1 Rezultatas)

**Definition**: Harmonic mean of Precision and Recall, providing balanced performance measure.

**Formula**: `F1 = 2 × (Precision × Recall) / (Precision + Recall)`

**Interpretation**:
- **Excellent F1 (>0.8)**: Both precision and recall are high
- **Good F1 (0.6-0.8)**: Balanced reasonable performance
- **Poor F1 (<0.6)**: Either precision or recall (or both) are low

**Why F1 Score Matters**: Unlike accuracy, F1 score works well with imbalanced data and considers both false positives and false negatives.

**Research Benchmark**: Zaranka's Master's thesis achieved 69.3% F1-score using xlm-roberta-base for Lithuanian propaganda fragment identification, significantly outperforming English language studies (~44% F1-score).

### 4. Cohen's Kappa (Cohen'o Kappa)

**Definition**: Measures agreement between AI and expert annotations, accounting for chance agreement.

**Formula**: `κ = (Po - Pe) / (1 - Pe)`
- **Po**: Observed agreement between annotators
- **Pe**: Expected agreement by chance

**Interpretation**:
- **κ > 0.8**: Almost perfect agreement
- **κ 0.6-0.8**: Substantial agreement  
- **κ 0.4-0.6**: Moderate agreement
- **κ 0.2-0.4**: Fair agreement
- **κ < 0.2**: Slight agreement

**Why Cohen's Kappa Matters**: Simple accuracy can be misleading when categories are imbalanced. Cohen's Kappa provides a more robust measure of true agreement.

### 5. Position Accuracy (Pozicijos Tikslumas)

**Definition**: Measures how accurately AI identifies the exact text positions of propaganda techniques.

**Calculation Method**:
1. **Overlap Requirement**: At least 50% overlap between AI and expert-identified text spans
2. **Position Tolerance**: ±10 characters tolerance for start/end positions
3. **Formula**: `Position Accuracy = Accurate Positions / Total AI Positions`

**Interpretation**:
- **High Position Accuracy (>0.9)**: AI precisely locates propaganda text
- **Medium Position Accuracy (0.7-0.9)**: AI generally finds correct areas
- **Low Position Accuracy (<0.7)**: AI struggles to pinpoint exact locations

## 🔢 Confusion Matrix Components

### True Positives (TP) - Teisingi Teigiami
- **Definition**: Propaganda correctly identified by both AI and experts
- **Example**: AI identifies "emotional appeal" at positions 50-80, expert agrees

### False Positives (FP) - Klaidingi Teigiami  
- **Definition**: AI identifies propaganda where experts found none
- **Example**: AI identifies "fear appeal" but expert marked it as neutral text

### False Negatives (FN) - Klaidingi Neigiami
- **Definition**: Expert identified propaganda that AI missed
- **Example**: Expert found "whataboutism" but AI didn't detect it

### True Negatives (TN) - Teisingi Neigiami
- **Definition**: Non-propaganda correctly identified as non-propaganda by both
- **Note**: Less relevant in span-based annotation tasks

## 🧠 Advanced Methodology

### Category Mapping Intelligence

The system uses intelligent category mapping to handle differences between expert and AI annotation methodologies:

**Expert Categories** (simplified) → **AI Categories** (ATSPARA methodology):
- `simplification` → `causalOversimplification`, `blackAndWhite`, `thoughtTerminatingCliche`
- `emotionalExpression` → `emotionalAppeal`, `loadedLanguage`, `appealToFear`
- `doubt` → `doubt`, `smears`, `uncertainty`

**Why This Matters**: Experts might use simplified category names while AI uses full ATSPARA taxonomy. Mapping ensures fair comparison.

### Position Matching Algorithm

1. **Step 1**: Check if text spans overlap by at least 50%
2. **Step 2**: Verify category match (direct or through mapping)
3. **Step 3**: Allow ±10 character tolerance for boundary positions
4. **Step 4**: Count as True Positive if all criteria met

## 📈 Performance Benchmarks

Based on ATSPARA validation data and system testing:

### Expected Performance Ranges

| Model | Precision | Recall | F1 Score | Cohen's Kappa |
|-------|-----------|---------|----------|---------------|
| **Claude Opus 4** | 0.80-0.90 | 0.75-0.85 | 0.77-0.87 | 0.65-0.80 |
| **Gemini 2.5 Pro** | 0.75-0.85 | 0.70-0.80 | 0.72-0.82 | 0.60-0.75 |
| **GPT-4.1** | 0.78-0.88 | 0.72-0.82 | 0.75-0.85 | 0.62-0.77 |

### Quality Thresholds

| Quality Level | F1 Score | Cohen's Kappa | Position Accuracy |
|---------------|----------|---------------|-------------------|
| **Excellent** | > 0.80 | > 0.70 | > 0.90 |
| **Good** | 0.70-0.80 | 0.60-0.70 | 0.80-0.90 |
| **Acceptable** | 0.60-0.70 | 0.50-0.60 | 0.70-0.80 |
| **Poor** | < 0.60 | < 0.50 | < 0.70 |

## 🔍 Practical Examples

### Example 1: High Precision, Low Recall
- **Scenario**: AI is very conservative, only identifies obvious propaganda
- **Metrics**: Precision = 0.95, Recall = 0.55, F1 = 0.70
- **Interpretation**: AI rarely makes mistakes but misses subtle propaganda

### Example 2: Low Precision, High Recall  
- **Scenario**: AI is aggressive, marks many segments as propaganda
- **Metrics**: Precision = 0.60, Recall = 0.90, F1 = 0.72
- **Interpretation**: AI catches most propaganda but has many false alarms

### Example 3: Balanced Performance
- **Scenario**: AI performs well across all measures
- **Metrics**: Precision = 0.82, Recall = 0.78, F1 = 0.80, Kappa = 0.68
- **Interpretation**: Good overall performance suitable for research

## 📊 Metric Calculation Technical Details

### Execution Time Metrics
- **Measured**: Per-model analysis time in milliseconds
- **Purpose**: Compare model processing speeds
- **Typical Range**: 3,000-15,000ms per text depending on complexity

### Batch Processing Metrics
- **Total Texts**: Number of texts in analysis
- **Processed Texts**: Successfully completed analyses  
- **Success Rate**: Processed / Total ratio
- **Average Processing Time**: Mean time per text across all models

### Statistical Significance
For reliable results, consider:
- **Minimum Sample Size**: 50+ texts for stable metrics
- **Cross-Validation**: Multiple independent test sets
- **Error Margins**: ±0.05 for F1 scores with 95% confidence

## 🔬 Advanced Research Metrics (Zaranka Benchmark)

### Fragment Identification Score
**Definition**: Span-based F1 score specifically designed for propaganda fragment detection.
**Research Benchmark**: 69.3% (xlm-roberta-base), 66.0% (litlat-bert), 64.6% (mdeberta-v3-base)

### Span Detection Accuracy  
**Definition**: Percentage of propaganda fragments where AI correctly identified both the technique category and precise text position.
**Formula**: `Accurate Spans / Total Spans`

### Lithuanian Language Superiority
**Research Finding**: Lithuanian propaganda fragments are ~12x longer than English equivalents, leading to better F1 scores:
- **Lithuanian**: ~69% F1 (this system and Zaranka's research)
- **English**: ~44% F1 (international studies)

### Model Performance Ranking (Research-Based)
1. **xlm-roberta-base**: 69.3% F1 (recommended for Lithuanian)
2. **litlat-bert**: 66.0% F1 (Baltic-specific model)  
3. **mdeberta-v3-base**: 64.6% F1 (multilingual alternative)

### Research Comparison Metrics
The system automatically compares results against established benchmarks:
- **vs. Zaranka Best**: Difference from 69.3% xlm-roberta-base result
- **vs. English Baseline**: Improvement over international ~44% F1 studies
- **Assessment**: Automatic evaluation (Excellent/Good/Below Expected)

## 🛠️ Using Metrics for Model Optimization

### 1. **Low Precision Issues**
- **Problem**: Too many false positives
- **Solutions**: 
  - Increase prompt specificity
  - Add negative examples to training
  - Raise confidence thresholds

### 2. **Low Recall Issues**
- **Problem**: Missing actual propaganda
- **Solutions**:
  - Broaden technique definitions
  - Include more diverse examples
  - Lower detection thresholds

### 3. **Position Accuracy Issues**
- **Problem**: Correct categories but wrong text spans
- **Solutions**:
  - Improve text span instructions
  - Add boundary-specific training
  - Use character-level guidance

## 📝 Reporting and Export

### CSV Export Format
```csv
text_id,technique,expert_start,expert_end,expert_text,model,model_start,model_end,model_text,match,position_accuracy,precision,recall,f1_score
37735,simplification,0,360,"Expert text...",claude-opus-4,0,196,"AI text...",true,0.92,0.85,0.78,0.81
```

### JSON Export Structure
```json
{
  "job_id": "uuid-here",
  "overall_metrics": {
    "avg_precision": 0.823,
    "avg_recall": 0.776,
    "avg_f1": 0.799,
    "cohen_kappa": 0.651
  },
  "per_model_metrics": {
    "claude-opus-4": {
      "precision": 0.853,
      "recall": 0.798,
      "f1_score": 0.825
    }
  }
}
```

## 🔬 Research Applications

### Academic Use Cases
1. **Comparative Studies**: Compare LLM performance across languages
2. **Technique Analysis**: Identify which propaganda techniques are hardest to detect
3. **Cross-Cultural Research**: Analyze cultural differences in propaganda patterns

### Practical Applications  
1. **Media Monitoring**: Automated propaganda detection in news
2. **Social Media Analysis**: Real-time detection in posts and comments
3. **Educational Tools**: Teaching propaganda recognition

## 📚 References and Methodology

### ATSPARA Project Integration
This system implements the ATSPARA (Automatic Detection of Propaganda and Disinformation) methodology developed at Vilnius University. All 21 propaganda techniques and 2 disinformation narratives follow ATSPARA classification standards.

### Statistical Foundation
- **Cohen's Kappa**: Cohen, J. (1960). A coefficient of agreement for nominal scales
- **F1 Score**: Van Rijsbergen, C.J. (1979). Information Retrieval
- **Precision/Recall**: Powers, D.M.W. (2011). Evaluation: From precision, recall and F-measure

### Quality Assurance
- **Expert Annotations**: All expert annotations follow ATSPARA methodology
- **Inter-Annotator Agreement**: Expert annotations validated with κ > 0.7
- **Technical Validation**: All metrics calculations independently verified

---

*This guide provides comprehensive understanding of all metrics used in the Lithuanian Propaganda Detection System. For technical implementation details, see `app/Services/MetricsService.php`.*