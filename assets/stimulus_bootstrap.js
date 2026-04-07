import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();
// Expose the Stimulus application so legacy page scripts can reach controllers
// (e.g. agenda/planning use window.Stimulus.getControllerForElementAndIdentifier
// to drive the calendar UI kit component).
window.Stimulus = app;
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
