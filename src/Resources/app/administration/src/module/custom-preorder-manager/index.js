import './page/custom-preorder-manager-list';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('custom-preorder-manager', {
    type: 'plugin',
    name: 'custom-preorder-manager',
    title: 'custom-preorder-manager.general.mainMenuItemGeneral',
    description: 'custom-preorder-manager.general.descriptionTextModule',
    color: '#1a1a2e',
    icon: 'regular-shopping-bag',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'custom-preorder-manager-list',
            path: 'list',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.viewer',
            },
        },
    },

    navigation: [{
        label: 'custom-preorder-manager.general.mainMenuItemGeneral',
        color: '#1a1a2e',
        path: 'custom.preorder.manager.list',
        icon: 'regular-shopping-bag',
        parent: 'sw-order',
        privilege: 'order.viewer',
        position: 100,
    }],
});
