import template from './custom-preorder-manager-list.html.twig';
import './custom-preorder-manager-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-preorder-manager-list', {
    template,
    inject: ['repositoryFactory'],
    mixins: [Mixin.getByName('listing')],

    data() {
        return {
            items: null,
            isLoading: false,
            total: 0,
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },
        defaultCriteria() {
            const criteria = new Criteria();
            criteria.setPage(this.page);
            criteria.setLimit(this.limit);
            criteria.addFilter(Criteria.equals('tags.name', 'Vorbestellung'));
            criteria.addAssociation('orderCustomer.customer');
            criteria.addAssociation('lineItems');
            return criteria;
        },
        columns() {
            return [
                { property: 'orderNumber', label: this.$tc('custom-preorder-manager.list.columnOrderNumber'), rawData: true },
                { property: 'orderCustomer.firstName', label: this.$tc('custom-preorder-manager.list.columnCustomer'), rawData: true },
                { property: 'orderDateTime', label: this.$tc('custom-preorder-manager.list.columnDate'), rawData: true },
                { property: 'stateMachineState.name', label: this.$tc('custom-preorder-manager.list.columnStatus'), rawData: true },
                { property: 'amountTotal', label: this.$tc('custom-preorder-manager.list.columnAmount'), rawData: true, align: 'right' },
            ];
        },
    },

    methods: {
        getList() {
            this.isLoading = true;
            this.orderRepository.search(this.defaultCriteria, Shopware.Context.api).then((result) => {
                this.total = result.total;
                this.items = result;
                this.isLoading = false;
            });
        },
    },
});
