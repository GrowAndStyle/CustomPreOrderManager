import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

export default class PreOrderManagerPlugin extends Plugin {
    init() {
        this.client = new HttpClient();
        this._registerEvents();
    }

    _registerEvents() {
        // Event-Handler für dynamische Interaktionen
    }
}
