'use strict';
// Class definition

var KTDatatableHtmlTableDemo = function() {
  // Private functions

  // demo initializer
  var demo = function() {

    var datatable = $('#kt_datatable').KTDatatable({
      pagination: false,
      translate:{
        records : {noRecords: 'No tickets found'},
      },
      data: {
        saveState: {cookie: false},
      },
      search: {
        input: $('#kt_datatable_search_query'),
        key: 'generalSearch',
      },
      layout: {
        class: 'datatable-bordered',
        scroll: true,
        customScrollbar: true
      },
      columns: [
        {
          field: 'ID',
          autoHide: false
        },
        {
          field: 'Ticket Title',
          autoHide: false
        },
        {
          field: 'Date Created',
          autoHide: false
        },
        {
          field: 'Item Type',
          autoHide: false
        },
        {
          field: 'Support Group',
          autoHide: false
        },
        {
          field: 'Status',
          autoHide: false
        },
        {
          field: 'Rejected Amount',
          autoHide: false
        },
        {
          field: 'Pending collection',
          autoHide: false
        },
        {
          field: 'Sub Status',
          autoHide: false
        },
        {
          field: 'Customer ID',
          autoHide: false
        },
        {
          field: 'Customer Name',
          autoHide: false
        },
        {
          field: 'Market Segment',
          autoHide: false
        },
        {
          field: 'Creator',
          autoHide: false
        },
        {
          field: 'Transfer to concession paper cycle status',
          autoHide: false
        },
        {
          field: 'Customer Sub-type',
          autoHide: false
        },
        {
          field: 'CN Number',
          autoHide: false
        },
        {
          field: 'CN Amount',
          autoHide: false
        },
        {
          field: 'Validation Status',
          autoHide: false
        },

      ]
      
    });

    
    $('#kt_datatable_search_type').on('change', function() {
      datatable.search($(this).val().toLowerCase(), 'Type');
    });
  };

  return {
    // Public functions
    init: function() {
      // init dmeo
      demo();
    },
  };
}();

jQuery(document).ready(function() {
  KTDatatableHtmlTableDemo.init();
});
