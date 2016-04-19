define('VisualEditorTourExperiment', ['jquery', 'wikia.loader', 'wikia.mustache', 'mw', 'wikia.tracker'],
	function ($, loader, mustache, mw, tracker) {
		'use strict';

		var track = tracker.buildTrackingFunction({
				category: 've-editing-tour',
				trackingMethod: 'analytics'
			});

		function Tour(tourConfig, labelPrefix) {
			this.labelPrefix = labelPrefix;
			this.tourConfig = tourConfig;
			this.steps = [];
		}

		Tour.prototype.start = function() {
			this.step = -1;
			loader({
				type: loader.MULTI,
				resources: {
					mustache: 'extensions/wikia/VisualEditorTourExperiment/templates/' +
					'VisualEditorTourExperiment_content.mustache'
				}
			}).done(this._setupTour.bind(this));
		};

		Tour.prototype.destroyStep = function(step) {
			var tourStepData = this.steps[step],
				$element = tourStepData ? tourStepData.$element : null;

			if ($element) {
				$element.popover('destroy');
			}
		};

		Tour.prototype.openStep = function(step) {
			var tourStepData = this.steps[step],
				$element = tourStepData ? tourStepData.$element : null;

			if (!$element) {
				return;
			}

			$element.popover({
				content: tourStepData.content,
				html: true,
				placement: this.tourConfig[step].placement,
				trigger: 'manual'
			});

			$element.popover('show');

			track({
				action: tracker.ACTIONS.IMPRESSION,
				label: this.labelPrefix + 'tour-step-' + this.step
			});
		};

		Tour.prototype.nextHandle = function() {
			this._setDisabled();
			this.next();
			track({
				action: tracker.ACTIONS.CLICK,
				label: this.labelPrefix + 'next-go-to-' + this.step
			});
		};

		Tour.prototype.next = function() {
			if (this.step === this.steps.length - 1) {
				this.destroyStep(this.step);
				track({
					action: tracker.ACTIONS.CLICK,
					label: this.labelPrefix + 'tour-complete'
				});
				return;
			}
			this.destroyStep(this.step);
			this.openStep(++this.step);
		};

		Tour.prototype.prevHandle = function() {
			this.destroyStep(this.step);
			this.openStep(--this.step);
			track({
				action: tracker.ACTIONS.CLICK,
				label: this.labelPrefix + 'next-go-to-' + this.step
			});
		};

		Tour.prototype.close = function() {
			this._setDisabled();
			this.destroyStep(this.step);
			track({
				action: tracker.ACTIONS.CLICK,
				label: this.labelPrefix + 'close'
			});
			track({
				action: tracker.ACTIONS.CLICK,
				label: this.labelPrefix + 'close-' + this.step
			});
		};

		Tour.prototype._setDisabled = function() {
			if(!$.cookie('vetourdisabled')) {
				$.cookie('vetourdisabled', 1, {expires: 30});
			}
		};

		Tour.prototype._setupTour = function (assets) {
			var $body = $('body');

			mw.hook('ve.cancelButton').add(function() {
				$body.off('.VETour');
				this.destroyStep(this.step);
			}.bind(this));

			$body.on('click.VETour', '.ve-tour-next', this.nextHandle.bind(this));
			$body.on('click.VETour', '.ve-tour-prev', this.prevHandle.bind(this));
			$body.on('click.VETour', '.ve-tour-experiment .close', this.close.bind(this));

			this.contentTemplate = assets.mustache[0];
			this.tourConfig.forEach(this._setupStep.bind(this));
			// Set delay to let VE show animation finish and position first step properly
			setTimeout(this.next.bind(this), 200);
		};

		Tour.prototype._setupStep = function (item, id) {
			var buttonLabel = id === this.tourConfig.length - 1 ? 'Start editing' : 'Next',
				showPrev = id > 0;

			this.steps[id] = {
				$element: $(item.selector),
				content: mustache.render(this.contentTemplate, {
					title: item.title,
					description: item.description,
					buttonLabel: buttonLabel,
					showPrev: showPrev
				})
			};
		};


		return Tour;
	}
);
