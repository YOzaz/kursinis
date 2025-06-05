# JSON Format Specification for Batch Analysis

This document describes the required JSON format for uploading batch analysis files to the propaganda analysis system.

## Overview

The system accepts JSON files containing multiple texts for analysis. Each text should include the actual content and optionally expert annotations for comparison metrics.

## JSON Structure

### Root Format
```json
[
  {
    "id": "unique_text_identifier",
    "data": {
      "content": "The actual text content to be analyzed..."
    },
    "annotations": [
      // Optional: Expert annotations in Label Studio format
    ]
  }
]
```

### Required Fields

- **`id`** (string|number): Unique identifier for the text
- **`data.content`** (string): The text content to be analyzed

### Optional Fields

- **`annotations`** (array): Expert annotations for comparison metrics

## Example

### Basic Example (No Expert Annotations)
```json
[
  {
    "id": "text_001",
    "data": {
      "content": "Vis pirma nusiimkim spalvotus vaikišius akinėlius, kuriuos mums kasdien uždeda ekranai iš visų pasaulio šalių..."
    }
  },
  {
    "id": "text_002", 
    "data": {
      "content": "Kitas tekstas analizei..."
    }
  }
]
```

### Advanced Example (With Expert Annotations)
```json
[
  {
    "id": "text_001",
    "data": {
      "content": "Vis pirma nusiimkim spalvotus vaikišius akinėlius, kuriuos mums kasdien uždeda ekranai iš visų pasaulio šalių..."
    },
    "annotations": [
      {
        "id": 828,
        "completed_by": 14,
        "result": [
          {
            "id": "jNnP69mzrh",
            "type": "choices",
            "value": {
              "choices": ["yes"]
            },
            "origin": "manual",
            "to_name": "content",
            "from_name": "primaryChoice"
          },
          {
            "id": "id0yi0Cbh5",
            "type": "labels",
            "value": {
              "end": 360,
              "text": "Vis pirma nusiimkim spalvotus vaikišius akinėlius...",
              "start": 0,
              "labels": ["simplification"]
            },
            "origin": "manual",
            "to_name": "content",
            "from_name": "label"
          }
        ]
      }
    ]
  }
]
```

## Expert Annotations Format

Expert annotations follow the [Label Studio](https://labelstud.io/) export format. This allows for:

- **Propaganda Classification**: Whether the text contains propaganda (`primaryChoice`)
- **Technique Annotations**: Specific propaganda techniques found in text segments (`labels`)
- **Disinformation Narratives**: Broader narrative classifications (`desinformationTechnique`)

### Annotation Types

1. **Primary Choice** (`type: "choices"`):
   - Indicates whether the text is predominantly propaganda
   - Values: `["yes"]` or `["no"]`

2. **Technique Labels** (`type: "labels"`):
   - Specific text segments marked with propaganda techniques
   - Includes start/end positions and technique labels

3. **Disinformation Narratives** (`type: "choices"`):
   - Broader narrative classifications
   - Multiple narratives can be selected

## File Requirements

- **Format**: JSON (.json extension)
- **Encoding**: UTF-8
- **Size**: Maximum 50MB per file
- **Structure**: Must be a valid JSON array
- **Text Length**: Individual texts should be reasonable length (recommended < 100KB each)

## Validation

The system will validate:
- JSON syntax and structure
- Required fields presence
- Text content is not empty
- Unique text IDs within the file

## Tips for Best Results

1. **Text Quality**: Ensure texts are clean and properly formatted
2. **Unique IDs**: Use meaningful, unique identifiers for each text
3. **Expert Annotations**: Include expert annotations for comparison metrics
4. **File Size**: Split very large datasets into multiple files for better performance

## API Integration

Once uploaded, the system will:
1. **Parse** the JSON structure
2. **Validate** the format and content
3. **Process** texts using selected AI models
4. **Generate** propaganda analysis results
5. **Calculate** comparison metrics (if expert annotations provided)

## Error Handling

Common errors and solutions:
- **Invalid JSON**: Check syntax with a JSON validator
- **Missing Required Fields**: Ensure all texts have `id` and `data.content`
- **Duplicate IDs**: Make sure all text IDs are unique
- **Empty Content**: All texts must have non-empty content

For technical support or questions about the JSON format, please refer to the main documentation or contact the development team.