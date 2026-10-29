<?php
/**
 * Samoocena pracodawcy „Jak inkluzywna jest Twoja firma?".
 *
 * Rekomendacja nr 5 z benchmarku (WEPs Gap Analysis Tool, UN LGBTIQ+ Tool,
 * Bilan Diversité): krótkie narzędzie samooceny z wynikiem i rekomendacją.
 * W benchmarku oznaczone priorytetem WYSOKIM, bo jest najskuteczniejszym
 * kanałem rekrutacji pracodawców do warsztatów z zadań 2–3 wniosku.
 *
 * DWIE DECYZJE, KTÓRE WARTO ZACHOWAĆ PRZY ZMIANACH
 * ------------------------------------------------
 * 1. Wynik liczy się w przeglądarce i nic nie trafia na serwer. Firma nie musi
 *    przyznawać się nam do słabych praktyk, żeby poznać wynik — to usuwa
 *    największą barierę wejścia i znaczy, że narzędzie nie przetwarza
 *    żadnych danych osobowych.
 * 2. Pytania są sformułowane opisowo, a nie ocennie („czy macie spisaną
 *    procedurę" zamiast „czy jesteście dyskryminujący"). Ton oceniający
 *    powoduje zawyżanie odpowiedzi i psuje wartość narzędzia.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pytania kwestionariusza pogrupowane w obszary.
 *
 * Punktacja: 0 = nie, 1 = częściowo, 2 = tak. Maksimum 30 punktów.
 *
 * @return array
 */
function sdc_assessment_questions() {
	return array(
		array(
			'obszar'  => __( 'Rekrutacja', 'stop-dyskryminacji-core' ),
			'pytania' => array(
				__( 'Ogłoszenia o pracę są sprawdzane pod kątem sformułowań wykluczających — wieku, płci, sprawności, znajomości języka ponad realną potrzebę.', 'stop-dyskryminacji-core' ),
				__( 'W ogłoszeniach podajemy widełki wynagrodzenia.', 'stop-dyskryminacji-core' ),
				__( 'Rozmowy rekrutacyjne prowadzimy według z góry ustalonego zestawu pytań, takiego samego dla wszystkich kandydatów.', 'stop-dyskryminacji-core' ),
				__( 'Proces rekrutacji jest dostępny dla osób z niepełnosprawnościami — od formularza po miejsce rozmowy.', 'stop-dyskryminacji-core' ),
			),
		),
		array(
			'obszar'  => __( 'Wynagrodzenia i awanse', 'stop-dyskryminacji-core' ),
			'pytania' => array(
				__( 'Co najmniej raz w roku analizujemy różnice w wynagrodzeniach między kobietami a mężczyznami na porównywalnych stanowiskach.', 'stop-dyskryminacji-core' ),
				__( 'Kryteria awansu są spisane i znane pracownikom.', 'stop-dyskryminacji-core' ),
				__( 'Powrót z urlopu rodzicielskiego lub długiej nieobecności nie pogarsza pozycji pracownika.', 'stop-dyskryminacji-core' ),
			),
		),
		array(
			'obszar'  => __( 'Procedury i reagowanie', 'stop-dyskryminacji-core' ),
			'pytania' => array(
				__( 'Mamy spisaną politykę antymobbingową i antydyskryminacyjną, a pracownicy wiedzą, gdzie ją znaleźć.', 'stop-dyskryminacji-core' ),
				__( 'Istnieje jasna ścieżka zgłoszenia nierównego traktowania, w tym możliwość ominięcia bezpośredniego przełożonego.', 'stop-dyskryminacji-core' ),
				__( 'Zgłoszenia są rozpatrywane w określonym terminie, a zgłaszający dostaje informację zwrotną.', 'stop-dyskryminacji-core' ),
				__( 'Osoby zgłaszające i świadkowie są chronieni przed działaniami odwetowymi.', 'stop-dyskryminacji-core' ),
			),
		),
		array(
			'obszar'  => __( 'Kultura pracy i kompetencje', 'stop-dyskryminacji-core' ),
			'pytania' => array(
				__( 'Kadra kierownicza przeszła szkolenie z równego traktowania w ciągu ostatnich dwóch lat.', 'stop-dyskryminacji-core' ),
				__( 'Stosujemy elastyczne formy pracy tam, gdzie charakter stanowiska na to pozwala.', 'stop-dyskryminacji-core' ),
				__( 'Materiały wewnętrzne i komunikacja są dostępne dla osób, dla których polski nie jest pierwszym językiem.', 'stop-dyskryminacji-core' ),
				__( 'Zbieramy opinie pracowników o atmosferze pracy w sposób, który pozwala wypowiedzieć się anonimowo.', 'stop-dyskryminacji-core' ),
			),
		),
	);
}

/**
 * Progi wyniku i przypisane im komunikaty.
 *
 * @return array
 */
function sdc_assessment_bands() {
	return array(
		array(
			'min'   => 0,
			'max'   => 12,
			'label' => __( 'Podstawy dopiero powstają', 'stop-dyskryminacji-core' ),
			'opis'  => __( 'Większość elementów równego traktowania nie jest jeszcze opisana ani wdrożona. To częsta sytuacja w mikro i małych firmach — i dobry moment, żeby zacząć od jednej rzeczy: spisanej polityki i ścieżki zgłoszeń.', 'stop-dyskryminacji-core' ),
		),
		array(
			'min'   => 13,
			'max'   => 20,
			'label' => __( 'Jest fundament, brakuje systemu', 'stop-dyskryminacji-core' ),
			'opis'  => __( 'Część rozwiązań działa, ale opiera się na dobrej woli poszczególnych osób, a nie na procedurze. Warto przenieść je do dokumentów, żeby przetrwały zmianę kadry.', 'stop-dyskryminacji-core' ),
		),
		array(
			'min'   => 21,
			'max'   => 26,
			'label' => __( 'Dobry poziom, są luki', 'stop-dyskryminacji-core' ),
			'opis'  => __( 'Firma ma świadome podejście do równego traktowania. Kolejny krok to pomiar — analiza wynagrodzeń i regularne badanie atmosfery pracy.', 'stop-dyskryminacji-core' ),
		),
		array(
			'min'   => 27,
			'max'   => 30,
			'label' => __( 'Wysoki poziom dojrzałości', 'stop-dyskryminacji-core' ),
			'opis'  => __( 'Rozwiązania są spisane, znane pracownikom i sprawdzane. Warto podzielić się praktyką — zgłoś ją do naszej bazy dobrych praktyk.', 'stop-dyskryminacji-core' ),
		),
	);
}

/**
 * Renderowanie narzędzia samooceny.
 *
 * @return string
 */
function sdc_render_self_assessment() {
	$groups   = sdc_assessment_questions();
	$total    = 0;
	$question = 0;

	foreach ( $groups as $group ) {
		$total += count( $group['pytania'] ) * 2;
	}

	ob_start();
	?>
	<form class="sd-quiz" id="sd-quiz" novalidate aria-describedby="sd-quiz-instructions">
		<p id="sd-quiz-instructions"><?php esc_html_e( 'Odpowiedz na wszystkie 15 pytań, aby otrzymać wynik. Każde pytanie wymaga jednej odpowiedzi.', 'stop-dyskryminacji-core' ); ?></p>
		<div class="sd-quiz__progress" role="status" aria-atomic="true">
			<span id="sd-quiz-count"><?php echo esc_html( sprintf( __( 'Odpowiedziano na %1$d z %2$d pytań.', 'stop-dyskryminacji-core' ), 0, (int) ( $total / 2 ) ) ); ?></span>
		</div>
		<div class="sd-quiz__bar" aria-hidden="true"><span id="sd-quiz-bar" style="width:0%"></span></div>
		<div id="sd-quiz-errors" class="sd-error-summary" tabindex="-1" aria-labelledby="sd-quiz-errors-title" hidden>
			<h2 id="sd-quiz-errors-title"><?php esc_html_e( 'Uzupełnij brakujące odpowiedzi', 'stop-dyskryminacji-core' ); ?></h2>
			<p><?php esc_html_e( 'Wynik będzie dostępny po udzieleniu wszystkich odpowiedzi. Wybierz pytanie z listy:', 'stop-dyskryminacji-core' ); ?></p>
			<ul id="sd-quiz-missing"></ul>
		</div>

		<?php foreach ( $groups as $group ) : ?>
			<section>
				<h2><?php echo esc_html( $group['obszar'] ); ?></h2>
				<?php foreach ( $group['pytania'] as $text ) : ?>
					<?php $question++; ?>
					<fieldset class="sd-quiz__q" id="sd-quiz-q<?php echo (int) $question; ?>">
						<legend><?php echo esc_html( $question . '. ' . $text ); ?></legend>
						<?php
						$options = array(
							2 => __( 'Tak', 'stop-dyskryminacji-core' ),
							1 => __( 'Częściowo', 'stop-dyskryminacji-core' ),
							0 => __( 'Nie', 'stop-dyskryminacji-core' ),
						);
						foreach ( $options as $value => $label ) :
							?>
							<label class="sd-option">
								<input type="radio" name="q<?php echo (int) $question; ?>" value="<?php echo (int) $value; ?>" required>
								<span class="sd-option__title"><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>

		<div>
			<button type="submit" class="sd-btn sd-btn--primary sd-btn--lg">
				<?php esc_html_e( 'Pokaż wynik', 'stop-dyskryminacji-core' ); ?>
			</button>
		</div>

		<p class="sd-field__hint">
			<?php esc_html_e( 'Odpowiedzi nie są nigdzie wysyłane. Wynik liczy się w Twojej przeglądarce i znika po zamknięciu karty.', 'stop-dyskryminacji-core' ); ?>
		</p>
	</form>

	<?php
	/*
	 * Wynik dostaje fokus, więc musi mieć rolę i nazwę — inaczej czytnik ekranu
	 * ogłasza nienazwany kontener. Nagłówek H2 porządkuje też strukturę: podtytuł
	 * „Trzy rzeczy warte poprawy" (H3) należy do wyniku, a nie do ostatniego
	 * obszaru pytań (WCAG 1.3.1, 4.1.2).
	 */
	?>
	<div id="sd-quiz-result" class="sd-callout sd-callout--success" hidden tabindex="-1"
		role="region" aria-labelledby="sd-quiz-result-title">
		<span aria-hidden="true"><?php echo wp_kses( sdc_icon( 'check', 24 ), sdc_kses_svg() ); ?></span>
		<div>
			<h2 class="sd-sr-only" id="sd-quiz-result-title"><?php esc_html_e( 'Wynik samooceny', 'stop-dyskryminacji-core' ); ?></h2>
			<p id="sd-quiz-completed"></p>
			<span class="sd-callout__label" id="sd-quiz-band"></span>
			<p class="sd-score">
				<span class="sd-score__value" id="sd-quiz-score">0</span>
				<span class="sd-score__max">/ <?php echo (int) $total; ?> <?php esc_html_e( 'punktów', 'stop-dyskryminacji-core' ); ?></span>
			</p>
			<p id="sd-quiz-desc"></p>

			<h3><?php esc_html_e( 'Trzy rzeczy warte poprawy w pierwszej kolejności', 'stop-dyskryminacji-core' ); ?></h3>
			<ol id="sd-quiz-gaps"></ol>

			<p>
				<a class="sd-btn sd-btn--secondary" href="/publikacje/kodeks-rownosci-i-inkluzywnosci/"><?php esc_html_e( 'O Kodeksie Równości', 'stop-dyskryminacji-core' ); ?></a>
				<a class="sd-btn sd-btn--ghost" href="/warsztaty/"><?php esc_html_e( 'Zapisz się na bezpłatne warsztaty', 'stop-dyskryminacji-core' ); ?></a>
			</p>
			<p>
				<button type="button" class="sd-btn sd-btn--ghost" onclick="window.print()">
					<?php esc_html_e( 'Drukuj lub zapisz wynik jako PDF', 'stop-dyskryminacji-core' ); ?>
				</button>
			</p>
		</div>
	</div>

	<script>
	(function () {
		var form = document.getElementById('sd-quiz');
		if (!form) { return; }

		var bands = <?php echo wp_json_encode( array_values( sdc_assessment_bands() ) ); ?>;
		var total = <?php echo (int) $total; ?>;
		var count = form.querySelectorAll('fieldset').length;
		var progressText = <?php echo wp_json_encode( __( 'Odpowiedziano na %1$d z %2$d pytań.', 'stop-dyskryminacji-core' ) ); ?>;
		var completedText = <?php echo wp_json_encode( __( 'Ukończono 15 z 15 pytań. Data samooceny:', 'stop-dyskryminacji-core' ) ); ?>;
		var lastCount = 0;
		var result = document.getElementById('sd-quiz-result');
		var errors = document.getElementById('sd-quiz-errors');
		var missingList = document.getElementById('sd-quiz-missing');

		function missingQuestions() {
			return Array.prototype.filter.call(form.querySelectorAll('fieldset'), function (fs) { return !fs.querySelector('input:checked'); });
		}
		function showMissing(moveFocus) {
			var missing = missingQuestions();
			missingList.textContent = '';
			missing.forEach(function (fs) {
				var li = document.createElement('li');
				var link = document.createElement('a');
				link.href = '#' + fs.id;
				link.textContent = fs.querySelector('legend').textContent;
				link.addEventListener('click', function (event) { event.preventDefault(); fs.querySelector('input').focus(); });
				li.appendChild(link); missingList.appendChild(li);
			});
			errors.hidden = missing.length === 0;
			if (moveFocus && missing.length) { errors.focus(); }
			return missing.length;
		}

		function answered() {
			return form.querySelectorAll('input[type=radio]:checked').length;
		}

		function updateProgress() {
			var n = answered();
			if (n !== lastCount) {
				document.getElementById('sd-quiz-count').textContent = progressText.replace('%1$d', n).replace('%2$d', count);
				lastCount = n;
			}
			document.getElementById('sd-quiz-bar').style.width = (n / count * 100) + '%';
		}

		form.addEventListener('change', function () {
			updateProgress();
			result.hidden = true; // A previous score no longer describes the changed answers.
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (showMissing(true)) { result.hidden = true; return; }

			var score = 0;
			var gaps  = [];

			form.querySelectorAll('fieldset').forEach(function (fs) {
				var checked = fs.querySelector('input:checked');
				var value   = checked ? parseInt(checked.value, 10) : 0;
				score += value;
				// Do rekomendacji trafiają obszary ocenione najniżej.
				if (value < 2) {
					gaps.push({ text: fs.querySelector('legend').textContent.replace(/^\d+\.\s*/, ''), value: value });
				}
			});

			var band = bands[bands.length - 1];
			for (var i = 0; i < bands.length; i++) {
				if (score >= bands[i].min && score <= bands[i].max) { band = bands[i]; break; }
			}

            document.getElementById('sd-quiz-score').textContent = String(score);
			document.getElementById('sd-quiz-band').textContent  = band.label;
			document.getElementById('sd-quiz-desc').textContent  = band.opis;

			var list = document.getElementById('sd-quiz-gaps');
			list.innerHTML = '';
			gaps.sort(function (a, b) { return a.value - b.value; }).slice(0, 3).forEach(function (gap) {
				var li = document.createElement('li');
				li.textContent = gap.text;
				list.appendChild(li);
			});
			if (!gaps.length) {
				var li = document.createElement('li');
				li.textContent = <?php echo wp_json_encode( __( 'Nie wskazaliśmy luk — wszystkie obszary ocenione najwyżej.', 'stop-dyskryminacji-core' ) ); ?>;
				list.appendChild(li);
			}

			document.getElementById('sd-quiz-completed').textContent = completedText + ' ' + new Date().toLocaleDateString(document.documentElement.lang || 'pl');
			result.hidden = false;
			result.focus();
			result.scrollIntoView({ block: 'start', behavior: 'smooth' });
		});

		updateProgress();
		void total;
	}());
	</script>
	<?php
	return ob_get_clean();
}
