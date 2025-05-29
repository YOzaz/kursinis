# Vartotojo sąsajos funkcijos

## 🎨 Teksto žymėjimo vizualizacija

### Aprašymas
Interaktyvi teksto analizės sistema, kuri spalvų kodais pažymi propagandos technikas tekste ir suteikia galimybę palyginti skirtingų šaltinių anotacijas.

### Funkcionalumas

#### AI vs Ekspertų anotacijos
- **AI View**: Rodo automatiškai aptiktas propagandos technikas iš LLM modelių
- **Expert View**: Rodo ekspertų rankiniu būdu sukurtas anotacijas
- **Toggle perjungimas**: Greitas persijungimas tarp skirtingų anotacijų tipų

#### Spalvų kodavimas
Kiekviena ATSPARA propagandos technika turi unikalų spalvos kodą:

| Technika | Spalva | Aprašymas |
|----------|--------|-----------|
| `emotionalAppeal` | #ff6b6b | Apeliavimas į jausmus |
| `appealToFear` | #ff8e53 | Apeliavimas į baimę |
| `loadedLanguage` | #4ecdc4 | Vertinamoji leksika |
| `nameCalling` | #45b7d1 | Etiketių klijavimas |
| `exaggeration` | #96ceb4 | Perdėtas vertinimas |
| `glitteringGeneralities` | #ffeaa7 | Blizgantys apibendrinimai |
| `whataboutism` | #dda0dd | Whataboutism |
| `redHerring` | #98d8c8 | Red Herring |
| `strawMan` | #f7dc6f | Straw Man |
| `causalOversimplification` | #bb8fce | Supaprastinimas |

#### Interaktyvi legenda
- **Dinaminė legenda**: Rodo tik tas technikas, kurios buvo aptiktos konkrečiame tekste
- **Aprašymai**: Paaiškinimas apie kiekvieną propagandos techniką lietuvių kalba
- **Spalvų indikatoriai**: Vizualus ryšys tarp legendos ir pažymėto teksto

#### API integracija
- **Real-time įkėlimas**: Anotacijos gaunamos per `/api/text-annotations/{id}` endpoint
- **Progreso indikatorius**: Loading spinner duomenų įkėlimo metu
- **Klaidos valdymas**: Aiškūs pranešimai apie nepavykusius užklausas

### Techninis sprendimas
```javascript
// Automatinis teksto žymėjimas su anotacijomis
function displayHighlightedText(content, annotations, legend) {
    // Surikiuoja anotacijas pagal poziciją
    const sortedAnnotations = [...annotations].sort((a, b) => a.start - b.start);
    
    // Sukuria HTML su spalvų kodais
    let highlightedContent = '';
    let lastIndex = 0;
    
    sortedAnnotations.forEach(annotation => {
        const color = techniqueColors[annotation.technique] || '#cccccc';
        highlightedContent += escapeHtml(content.substring(lastIndex, annotation.start));
        highlightedContent += `<span class="highlighted-annotation" 
                                     style="background-color: ${color}; padding: 2px 4px;"
                                     title="${annotation.technique}">${escapeHtml(annotation.text)}</span>`;
        lastIndex = annotation.end;
    });
}
```

## 📊 Dashboard grafikų sistema

### Chart.js integracija
Sistema naudoja Chart.js biblioteką interaktyviems grafikams kurti:

#### Modelių našumo palyginimas
- **Bar Chart**: Rodo precision, recall ir F1 score metrkas kiekvienam modeliui
- **Interaktyvūs elementai**: Hover tooltips su detaliais duomenimis
- **Responsyvus dizainas**: Prisitaiko prie ekrano dydžio

#### Technikų pasiskirstymas
- **Doughnut Chart**: Vizualizuoja dažniausiai aptiktas propagandos technikas
- **Spalvų kodavimas**: Atitinka teksto žymėjimo spalvas
- **Legenda**: Rodo technikų procentinį pasiskirstymą

#### Statistikų eksportas
- **JSON formatas**: Pilni duomenys programiniam apdorojimui
- **CSV formatas**: Duomenys Excel analizei
- **Excel formatas**: Formatuoti duomenys su stiliais

### Konfigūracija
```javascript
// Chart.js konfigūracija modelių našumui
const performanceChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: modelNames,
        datasets: [{
            label: 'F1 Score (%)',
            data: f1Scores,
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
```

## 🔍 Paieškos ir filtravimo sistema

### Real-time paieška
- **Tikslus atitikimas**: Ieško pagal analizės pavadinimą, ID ir aprašymą
- **Momentinis atsakas**: JavaScript filtravimas be serverio užklausų
- **Žymėjimas**: Nėra rezultatų pranešimas su aiškiais veiksmais

### Filtravimo kategorijos

#### Statusų filtras
| Status | Aprašymas |
|--------|-----------|
| `completed` | Sėkmingai baigtos analizės |
| `processing` | Šiuo metu vykdomos analizės |
| `failed` | Nepavykusios analizės |
| `pending` | Laukiančios eilėje analizės |

#### Tipų filtras
| Tipas | Aprašymas |
|-------|-----------|
| `standard` | Standartinis ATSPARA prompt |
| `custom` | Vartotojo sukurtas prompt |
| `repeat` | Pakartotinė analizė |

### JavaScript implementacija
```javascript
function applyFilters() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusValue = statusFilter.value;
    const typeValue = typeFilter.value;
    
    analysisCards.forEach(card => {
        let visible = true;
        
        // Paieškos filtras
        if (searchTerm) {
            const cardText = card.textContent.toLowerCase();
            visible = visible && cardText.includes(searchTerm);
        }
        
        // Statusų filtras
        if (statusValue && visible) {
            const statusBadge = card.querySelector('.badge');
            visible = visible && statusBadge.className.includes(`badge-${statusValue}`);
        }
        
        card.style.display = visible ? 'block' : 'none';
    });
}
```

## 🎛️ Vartotojo patirtis (UX)

### Responsive dizainas
- **Mobile-first**: Prisitaiko prie telefonų ekranų
- **Tablet optimizacija**: Efektyvus naudojimas planšetėse
- **Desktop funkcionalumas**: Pilnas funkcionalumas didesnės raiškos ekranuose

### Accessibility (prieinamumas)
- **Klaviatūros navigacija**: Tab key support visoms interaktyvioms funkcijoms
- **Screen reader support**: ARIA labels ir descriptions
- **Spalvų kontrastas**: WCAG 2.1 standartų atitiktis
- **Tooltip pagalba**: Papildomi paaiškinimai visoms funkcijoms

### Našumo optimizacija
- **Lazy loading**: Anotacijos kraunamos tik kai reikia
- **Client-side filtering**: Greitas atsakas be serverio užklausų
- **Optimizuoti grafikų renderiai**: Chart.js performance settings
- **Minimali DOM manipuliacija**: Efektyvus JavaScript kodas

## 🔧 Konfigūracija ir plėtojimas

### Spalvų temos pritaikymas
Spalvų schema yra konfigūruojama `techniqueColors` objekte:

```javascript
const techniqueColors = {
    'emotionalAppeal': '#ff6b6b',
    'appealToFear': '#ff8e53',
    // ... kitos technikos
};
```

### Naujų funkcijų pridėjimas
1. **Naujos technikos**: Pridėti spalvą į `techniqueColors` ir aprašymą į `getTechniqueDescription`
2. **Nauji grafikai**: Sukurti Chart.js instanciją su reikiamais duomenimis
3. **Nauji filtrai**: Pridėti naują select elementą ir filtrų logiką

### API endpointų plėtimas
Nauji anotacijų tipai gali būti pridėti per `/api/text-annotations` endpoint pridedant naują `view` parametro reikšmę.

---

Šios funkcijos sudaro modernų, interaktyvų vartotojo sąsają propagandos analizės sistemai, suteikiant tyrėjams ir praktikams intuityvius įrankius lietuviško teksto analizei.