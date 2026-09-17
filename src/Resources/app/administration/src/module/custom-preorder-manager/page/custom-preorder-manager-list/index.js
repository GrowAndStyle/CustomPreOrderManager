import template from './custom-preorder-manager-list.html.twig';
import './custom-preorder-manager-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-preorder-manager-list', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
            isLoading: false,
            total: 0,
            sortBy: 'orderDateTime',
            sortDirection: 'DESC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCriteria() {
            const criteria = new Criteria();
            criteria.setPage(this.page);
            criteria.setLimit(this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            } else {
                criteria.addSorting(Criteria.sort('orderDateTime', 'DESC'));
            }

            // Strikter Filter: Nur Bestellungen mit Tag "Vorbestellung"
            criteria.addFilter(Criteria.equals('tags.name', 'Vorbestellung'));

            // Flache Relationen für optimale Performance & Scoped Slots
            criteria.addAssociation('orderCustomer');
            criteria.addAssociation('stateMachineState');
            criteria.addAssociation('currency');
            criteria.addAssociation('lineItems');

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'orderNumber',
                    dataIndex: 'orderNumber',
                    label: this.$tc('custom-preorder-manager.list.columnOrderNumber'),
                    routerLink: 'sw.order.detail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'orderCustomer.firstName',
                    dataIndex: 'orderCustomer.firstName',
                    label: this.$tc('custom-preorder-manager.list.columnCustomer'),
                    allowResize: true,
                },
                {
                    property: 'orderDateTime',
                    dataIndex: 'orderDateTime',
                    label: this.$tc('custom-preorder-manager.list.columnDate'),
                    allowResize: true,
                },
                {
                    property: 'stateMachineState.name',
                    dataIndex: 'stateMachineState.name',
                    label: this.$tc('custom-preorder-manager.list.columnStatus'),
                    allowResize: true,
                },
                {
                    property: 'amountTotal',
                    dataIndex: 'amountTotal',
                    label: this.$tc('custom-preorder-manager.list.columnAmount'),
                    align: 'right',
                    allowResize: true,
                },
            ];
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    watch: {
        orderCriteria: {
            handler() {
                this.getList();
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getOrderStatusVariant(item) {
            const state = item?.stateMachineState?.technicalName;

            switch (state) {
                case 'completed':
                    return 'success';
                case 'in_progress':
                    return 'info';
                case 'cancelled':
                    return 'danger';
                default:
                    return 'neutral';
            }
        },

        getList() {
            this.isLoading = true;

            return this.orderRepository
                .search(this.orderCriteria, Shopware.Context.api)
                .then((result) => {
                    this.total = result.total;
                    this.items = result;
                    this.isLoading = false;
                })
                .catch(() => {
                    this.isLoading = false;
                    this.items = [];
                });
        },
    },
});

