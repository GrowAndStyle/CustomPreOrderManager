import PreOrderManagerPlugin from './plugin/preorder-manager.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('PreOrderManager', PreOrderManagerPlugin, '[data-preorder]');
