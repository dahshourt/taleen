window.Vue = require('vue').default;
Vue.component('SampleComponent', require('./components/ExampleComponent.vue').default);
new Vue({
    el: '#app'
});


