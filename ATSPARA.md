Instructions
Anotavimo proceso patikslinimų sąrašas
1. PD šaltinių ir autorių didesnei įvairovei LabelStudio padidinamas PD šaltinių skaičius (nuo 2 iki 5 ar 7) iš kurių atrenkami galimai PD požymių turintys straipsniai (pirmasis filtras). Reikėtų įtraukti, pvz., mūsų partnerių (LRT ir Delfi) anksčiau pateiktus straipsnius ir pagal galimybes atrinkti kuo daugiau ir įvairesnių PD temas atitinkančių straipsnių. Tikslas - įgalinti ML algoritmus ant pateiktų apmokymui straipsnių pavyzdžių geriau išmokyti atpažinti įvairias PD temas informacinio (dezinformacinio) karo kontekste.
2. Label Studio Cross-annotation projekte konkretaus anotuotojo visi straipsniai turi būti anotuojami nepriklausomai nuokitų anotuotojų sužymėjimų.
3. Objektyviai žymimos sutartos PD technikos, nepriklausomai iš kurios politinės stovyklos jos galėtų ateiti ir ką galėtų atstovauti. Tiesiog objektyviai be šališkumų sužymimos pastebėtos PD technikos pagal jų trumpus ir išsamesius apibrėžimus, kurie pateikti LabelStudio.
4. Jeigu anotuotojas/a mano, kad straipsnis su didele tikimybe ateina iš vidinės politinės kovos ir nėra inspiruotos iš trečiųjų šalių, tuomet pažymi 'vidinė politinė kova' tam skirtame papildomame laukelyje. Tai svarbu, nes leis mums vėliau tyrinėti vidinio politinio socialinio konteksto inspiruotas ir naudojamas PD technikas.
5. Visi anotuotojai turi žymėti 'Auksinis PD standartas' tiems straipsniams, kuriuose aiškiai dominuoja prokremliški naratyvai ir kurie vargu ar yra inspiruoti vidinės plotinės kovos. Šių straipsnių kol kas yra pažymėta labai mažai.
6. Svarbu, kad kuo daugiau būtų straipsnių su anotuotojų pažymėtomis PD temomis, nes tai straipsniai, po kuriais slepiasi ypač dominantys informacinio karo naratyvai. Jų analizė šiame projekte bus ypač svarbi.
7. 'Ar tai yra propaganda?' klausimo atveju: anotuotojai turi atsiriboti nuo galimų vidinių politinės kovos aspektų interpretavimo ir argumentuotai atsakyti į šį klausimą tik remiantis LabelStudio apsibrėžtais objektyviais propagandos apibrėžimais ir tuo kokią santykinę dalį nuo viso teksto propagandos fragmentai visam tekste sudaro, pvz., >40% tuomet sutartinai žymėti TAIP (nesvarbu iš kokio politinio konteksto ta propaganda yra kilusi). Jeigu, vis dėlto, konsoliduotas bendras sprendimas tarp anotuotojų nerandamas dėl kardinalaus nuomonių išsiskyrimo, tuomet tokie atvejai nepašalinami, o paliekamas variantas 'Neįmanoma nustatyti'. Vėliau tokie sudėtingi atvejai bus nagrinėjami detaliau, t.y. individualiai pagal anotuotojus, kur Jūratės svoris bus pagal anksčiau pateiktą schemą didžiausias.

Propagandos technikos


1. Emocinė raiška
Bandoma sukelti stiprius jausmus; emocinė leksika, etiketės, vertybiniai argumentai, hiperbolizavimas / sumenkinimas. • Apeliavimas į jausmus (Emotional Appeal / Appeal to strong emotions) Siekiama sukelti stiprius jausmus / emocijas, siekiant pakeisti nuomonę ar veiksmus.
• Apeliavimas į baimę (Appeal to fear / prejudice) Apeliavimas į baimę reiškia, jog propagandistai, siekdami sutelkti paramą, sėja visuomenėje paniką ir nerimą. Pavyzdžiui, Joseph Goebbels, Antrojo pasaulinio karo metu, pasinaudodamas nacionaline spauda, teigė, jog sąjungininkai siekia sunaikinti vokiečius.
Example 1: "either we go to war or we will perish" (this is also aBlack and White fallacy))
Example 2: "we must stop those refugees as they are terrorists"
• Vertinamoji, emocinė leksika (Loaded Language)
Vartojami stiprias asociacijas / konotacijas turintys žodžiai, siekiant paveikti skaitytojų požiūrį / suvokimą apie reiškinius, žmones ir pan.
Example 1: "[...] a lone lawmaker’s childish shouting. ".
Example 2: "how stupid and petty things have become in Washington"
• Etikečių klijavimas arba Argumentas prieš žmogų (angl. Name calling / Ad Hominem) Neigiamą konotaciją turinčių žodžių vartojimas siekiant sumenkinti priešininką arba paneigti priešingą požiūrį. Įžeidžiami žodžiai dažnai vartojami vietoje logiškai pagrįstų argumentų, apeliuojama į emocijas, o ne į protą. Propagandininkui būdinga dažnai klijuoti tam tikrą etiketę didesnei žmonių grupei, nors tokios priemonės taikymas iš tikrųjų būdingas tik nedidelei tikslinės grupės daliai Examples: "Republican congressweasels", "Bush the Lesser" (note that lesser does not refer to "the second", but it is pejorative)
• Perdėtas vertinimas / hiperbolizavimas arba sumenkinimas (Exaggeration or Minimisation) Hiperbolizuojama: kažkas aprašoma perdėtai (didesni, geresni, blogesni, nei yra iš tikrųjų, pvz., „geriausias iš geriausių“, „kokybė garantuota“ Sumenkinimas: kas nors pateikiama kaip mažiau svarbus ar mažesnis, nei yra iš tikrųjų (pvz., pasakoma, kad įžeidimas buvo tik pokštas).
Example 1: "Democrats bolted as soon as Trump’s speech ended in an apparent effort to signal, they can’t even stomach being in the same room as the president "
Example 2: "We’re going to have unbelievable intelligence"
Example 3: I was not fighting with her; we were just playing.
• „Blizgantys“ apibendrinimai (Vertybiniai žodžiai / vertybiniai argumentai) (angl. Glittering generalities (Virtue))
Apibūdina patrauklius, tačiau miglotus žodžius, kurie vartojami propagandoje. Užuot paaiškinęs tokių žodžių reikšmę, propagandininkas jais prisidengia gindamas savo poziciją. Vartojami be konteksto ar neįvardinus aiškios reikšmės tokie žodžiai auditorijai padeda sukelti tam tikrus jausmus. Jei propaganda sėkminga, šiais jausmais naudojamasi norint išlaikyti nekvestionuojamą propagandininko poziciją.
Remiantis vertybėmis, kuriama apgaulinga pasitikėjimo, teisingumo, pagarbos žmogaus laisvei ir teisėms atmosfera. Tai yra tikslinės auditorijos vertybių sistemai būdingi žodžiai, kurie sukuria teigiamą įvaizdį, kai vartojami asmeniui ar reiškiniui apibūdinti. Taika, laimė, saugumas, išmintingas vadovavimas, laisvė, tiesa ir t. t. yra vertybiniai žodžiai. Dauguma religingumą laiko vertybe, todėl naudoti religines asociacijas yra naudinga.

2. Whataboutism_Red Herring_Straw Man
Išsisukinėjimas; oponento pozicijos, jo teiginių menkinimas; dėmesio nukreipimas kitur.
• Whataboutism Bandoma diskredituoti oponento poziciją apkaltinant jį veidmainiavimu, tiesiogiai nepaneigiant jo argumentų. Example 1: a nation deflects criticism of its recent human rights violations by pointing to the history of slavery in the United States. Example 2: "Qatar spending profusely on Neymar, not fighting terrorism"
• Presenting Irrelevant Data (Red Herring) Nereikšmingi ir su aptariamu klausimu nesusiję dalykai siekiant nukreipti dėmesį kitur. Example 1: In politics, defending one’s own policies regarding public safety - “I have worked hard to help eliminate criminal activity. What we need is economic growth that can only come from the hands of leadership.” Example 2: "You may claim that the death penalty is an ineffective deterrent against crime -- but what about the victims of crime? How do you think surviving family members feel when they see the man who murdered their son kept in prison at their expense? Is it right that they should pay for their son's murderer to be fed and housed?"
• Misrepresentation of Someone's Position (Straw Man) Siekiant paneigti oponento teiginį, jis pakeičiamas panašiu teiginiu (šiaudinė baidyklė) ir tada paneigiamas, bet pirminis teiginys lieka nepaneigtas.
Example:
Zebedee: What is your view on the Christian God?
Mike: I don’t believe in any gods, including the Christian one.
Zebedee: So you think that we are here by accident, and all this design in nature is pure chance, and the universe just created itself?
Mike: You got all that from me stating that I just don’t believe in any gods?
Explanation: Mike made one claim: that he does not believe in any gods. From that, we can deduce a few things, like he is not a theist, he is not a practicing Christian, Catholic, Jew, or a member of any other religion that requires the belief in a god, but we cannot deduce that he believes we are all here by accident, nature is chance, and the universe created itself.

3. Supaprastinimas
Daroma prielaida, kad yra viena problemos priežastis; kaltė perkeliama vienam asmeniui/grupei, neanalizuojant problemos sudėtingumo.
• Supaprastinimas (Causal Oversimplification) Pateikiami paprasti atsakymai į sudėtingas socialines, politines, ekonomines, ar karines problemas. Perdėtas priežastinių ryšių supaprastinimas (darant prielaidą, kad yra viena priežastis, kai iš tikrųjų jų yra daugiau; tai apima kaltės perkėlimą vienam asmeniui ar žmonių grupei, netiriant problemos sudėtingumo)
Example 1: “President Trump has been in office for a month and gas prices have been skyrocketing. The rise in gas prices is because of President Trump.”
Example 2: The reason New Orleans was hit so hard with the hurricane was because of all the immoral people who live there.
Explanation: This was an actual argument seen in the months that followed hurricane Katrina. Ignoring the validity of the claims being made, the arguer is blaming a natural disaster on a group of people.
Example 3: if France had not have declared war on Germany then world war two would have never happened.
• Juoda-balta (Black-and-white Fallacy, Dictatorship)
„Juoda-balta“ vertinimas (du alternatyvūs variantai pateikiami kaip vienintelės galimybės; dar vadinama „netikruoju pasirinkimu“);
Pristatant tik dvi produkto ar idėjos puses: blogąja ir gerąją, yra propaguojama, jog geresnioji yra vienintelis tinkamas pasirinkimas. Pavyzdžiui: „Tu arba su mumis, arba prieš mus…“
Example 1: You must be a Republican or Democrat. You are not a Democrat. Therefore, you must be a Republican
Example 2: I thought you were a good person, but you weren’t at church today.
Explanation: The assumption here is that if one doesn't attend chuch, one must be bad. Of course, good people exist who don’t go to church, and good church-going people could have had a really good reason not to be in church.
• Klišės (Thought-terminating cliché)
Stereotipinis, dažnai vartojamas posakis, konstrukcija, štampas. Klišėmis vadinami pavieniai, nuolat pasikartojantys banalūs pasakymai. Žodžiai ar frazės, kurie neskatina kritinio mąstymo ir prasmingos diskusijos tam tikra tema. Paprastai tai yra trumpi, bendri sakiniai, kuriuose pateikiami iš pažiūros paprasti atsakymai į sudėtingus klausimus arba kurie atitraukia dėmesį nuo kitų minčių.
Examples: It is what it is; It's just common sense; You gotta do what you gotta do; Nothing is permanent except change; Better late than never; Mind your own business; Nobody's perfect; It doesn't matter; You can't change human nature.
• Šūkiai (Slogans)
Šūkis yra santrauka, žymi frazė, kuri gali apimti žymėjimą ir šabloninimą. Kitą vertus, šūkiai gali būti sukurti tam, kad palaikytų samprotautas mintis, praktiškai jie yra linkę veikti tiktai kaip emocionalūs kreipimaisi.
Example 1: "The more women at war . . . the sooner we win."
Example 2: "Make America great again!"

4. Neapibrėžtumas, sąmoningas neaiškios kalbos vartojimas (Obfuscation, Intentional vagueness, Confusion)
Vartojama neaiški kalba, kad auditorija žinutę galėtų interpretuoti savaip. Pavyzdžiui, argumente vartojama neaiški frazė su keliomis galimomis reikšmėmis, todėl ji neparemia išvados.
Example: It is a good idea to listen to victims of theft. Therefore, if the victims say to have the thief shot, then you should do that.
Explanation: the definition for "listen to" is equivocated here. In the first case it means listen to their personal account of the experience of being a victim of theft. Empathize with them. In the second case "listen to" means carry out a punishment of their choice.

5. Apeliavimas į autoritetą (Appeal to authority)
Cituojami garsūs, žinomi autoritetai, kurie remia propagandisto idėją, argumentus, poziciją ir veiksmus. Naudojant šią techniką yra cituojami garsūs, žinomi autoritetai, kurie remia propagandisto idėją, argumentus, poziciją ir veiksmus. Rėmimasis autoritetu (ekspertais, institucijomis, sektinais pavyzdžiais; teigiama, kad teiginys yra teisingas vien todėl, kad atitinkama institucija ar ekspertas pasakė, kad tai tiesa, nepateikus jokių kitų patvirtinančių įrodymų);
Pastaba: Čia patenka ir Testimonials.
Example: Richard Dawkins, an evolutionary biologist and perhaps the foremost expert in the field, says that evolution is true. Therefore, it's true.
Explanation: Richard Dawkins certainly knows about evolution, and he can confidently tell us that it is true, but that doesn't make it true. What makes it true is the preponderance of evidence for the theory.
Example 2: "According to Serena Williams, our foreign policy is the best on Earth. So we are in the right direction."
Details: since there is a chance that any authority can be wrong, it is reasonable to defer to an authority to support a claim, but the authority should not be the only justification to accept the claim, otherwise the Appeal-to-Authority fallacy is committed.

6. Mojavimas vėliava (Vėliavos kėlimas) arba Žaidimas stipriais nacionaliniais jausmais (Flag-waving)
Pastangos pateisinti veiksmą remiantis patriotiškumu ar teisinantis, kad veiksmas duos naudos šaliai / grupei žmonių. Tai pastangos pateisinti veiksmą remiantis patriotiškumu ar teisinantis, kad veiksmas tam tikru atžvilgiu duos naudos šaliai ar grupei žmonių.
Example 1: "patriotism mean no questions" (this is also a slogan)
Example 2: "entering this war will make us have a better future in our country."

7. Sekimas iš paskos (angl. bandwagon) Apeliavimas į bandos jausmą. Pasinaudojama tuo, kas dažnai vadinama „bandos jausmu“. Žmonės yra linkę priklausyti daugumai ir nenori likti nuošalyje, taigi ši technika manipuliuoja žmonėmis apeliuodama į jų instinktus.
Example 1: Would you vote for Clinton as president? 57% say yes
Example 2: 90% of citizens support our initiative. You should.

8. Abejojimas
Šmeižtas, abejonės dėl kieno nors patikimumo, pvz., „Ar jis pasirengęs tapti prezidentu?“
• Abejojimas (Doubt)
Abejojama grupės, asmens, dalyko patikimumu, pvz., „Ar jis pasirengęs tapti prezidentu?“. Example: A candidate talks about his opponent and says: Is he ready to be the Mayor?
• Šmeižtas (Smears) Bandymas pakenkti kieno nors reputacijai arba priversti suabejoti ja.
Meme Example: The combination of the image and the text conveys the idea that Biden is unpopular

9. Reductio ad hitlerum
Siekiama įtikinti nepritarti veiksmui / idėjai, nurodant, kad tai populiaru tarp grupių, kurių tikslinė auditorija nekenčia.
Example 1: "Do you know who else was doing that ? Hitler!"
Example 2: "Only one kind of person can think in that way: a communist."
10. Pakartojimas (Repetition)
Tekste kartojama ta pati žinutė.