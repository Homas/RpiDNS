/*
(c) Vadim Pavlov 2020
RpiDNS powered by https://ioc2rpz.net
*/

const gColors = ['#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0',
								 '#3F51B5', '#03A9F4', '#4CAF50', '#F9CE1D', '#FF9800',
								 '#33B2DF', '#546E7A', '#D4526E', '#13D8AA', '#A5978B',
								 '#4ECDC4', '#C7F464', '#81D4FA', '#546E7A', '#FD6A6A',
								 '#2B908F', '#F9A3A4', '#90EE7E', '#FA4443', '#69D2E7',
								 '#449DD1', '#F86624', '#EA3546', '#662E9B', '#C5D86D',
								 '#D7263D', '#1B998B', '#2E294E', '#F46036', '#E2C044',
								 '#662E9B', '#F86624', '#F9C80E', '#EA3546', '#43BCCD',
								 '#5C4742', '#A5978B', '#8D5B4C', '#5A2A27', '#C4BBAF',
								 '#A300D6', '#7D02EB', '#5653FE', '#2983FF', '#00B1F2'];

const io2c_app = new Vue({
  el: "#app",
  components: {
		apexchart: VueApexCharts,
  },
  data: {
		toggleMenu: 0, // show/hide menu
		cfgTab: 0,
		windowInnerWidth: 800,
		logs_height:150, //logs per page
		logs_updatetime:0, //last time the logs were updated
		hits_cp:1,
		hits_Filter:'', //RPZ hits filter
		hits_nrows:0,
		hits_pp:100, //per page
		qlogs_cp:1,
		qlogs_Filter:'', //Query logs filter
		qlogs_nrows:0,
		qlogs_pp:100, //per page

		qlogs_period:'30m',
		hits_period:'30m',
		dash_period:'30m',
		

		//hits_pFilter:'', //RPZ hits previous filter
		//hits_feventts:0,
		//hits_leventts:0,
		//hits_psort:['dtz','desc'],
		//hits_pcp:1,
		
    qlogs_fields: [
        { key: 'dtz', label: 'Local Time', sortable: true, formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
        { key: 'client_ip', label: 'Client IP', sortable: true, 'tdClass':'mw200 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
//        { key: 'server', label: 'Server', sortable: true},
        { key: 'mac', label: 'MAC', sortable: true, 'tdClass':'mw150 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
        { key: 'fqdn', label: 'Request', sortable: true, 'tdClass':'mw250'},
        { key: 'type', label: 'Type', sortable: true},
        { key: 'class', label: 'Class', sortable: true},
//        { key: 'options', label: 'Options', sortable: true},
        { key: 'cnt', label: 'Count', sortable: true},
        { key: 'action', label: 'Action', sortable: true},
    ],		

    hits_fields: [
        { key: 'dtz', label: 'Local Time', sortable: true,   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
        { key: 'client_ip', label: 'Client IP', sortable: true, 'tdClass':'mw200 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
        { key: 'mac', label: 'MAC', sortable: true, 'tdClass':'mw150 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
        { key: 'fqdn', label: 'Request', sortable: true, 'tdClass':'mw200'},
        { key: 'action', label: 'Action', sortable: true},
        { key: 'rule', label: 'Rule', sortable: true, 'tdClass':'mw300 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
	      { key: 'rule_type', label: 'Type', sortable: true},
//        { key: 'feed', label: 'Feed', sortable: true},
        { key: 'cnt', label: 'Count', sortable: true},
    ],
		
		dash_stats_fields:[
			{ key: 'name', label: 'Name', 'tdClass':'mw350'},
			{ key: 'cnt', label: 'Count'},			
		],

		period_options: [
			{ text: '30m', value: '30m' },
			{ text: '1h', value: '1h' },
			{ text: '1d', value: '1d'  },
			{ text: '1w', value: '1w'  },
			{ text: '30d', value: '30d' },
			{ text: 'custom', value: 'custom', disabled: true },
		],

		qperiod_options: [
			{ text: '30m', value: '30m' },
			{ text: '1h', value: '1h' },
			{ text: '1d', value: '1d'  },
			{ text: '1w', value: '1w'  },
			{ text: '30d', value: '30d' },
			{ text: 'custom', value: 'custom', disabled: true },
		],


			qps_series: [],
      qps_series_sample: [
				{name: 'qps', data: [[1564027200000,50],[1564030800000,100],[1564034400000,150],[1564038000000,100]]},
				{name: 'hits', data: [[1564027200000,10],[1564030800000,50],[1564034400000,50],[1564038000000,30]]},
			],
			
			qps_options: {
				colors: ['#008FFB','#FA4443'],
        chart: {
					id: 'qps-stats',
				//	stacked: true,
				},
				dataLabels: {
            enabled: false
        },
				xaxis: {
					type: 'datetime',
					labels: {datetimeUTC: false},
				},
				tooltip:{
					x:{
						format: 'dd MMM yyyy H:mm',
					}
				},
				yaxis: {
						//logarithmic: true,
						min: 0,
				},
				fill: {
					type: 'gradient',
					gradient: {
						opacityFrom: 0.6,
						opacityTo: 0.8,
					}
          },
      },
		
	},

  mounted: function () {
    if (window.location.hash) {
      var a=window.location.hash.split(/#|\//).filter(String);
      switch (a[0]){
        case "i2r":
          this.cfgTab=parseInt(a[1]);        
      };
			if (window.localStorage.getItem('toggleMenu')){
					this.toggleMenu=parseInt(window.localStorage.getItem('toggleMenu'));
			}
    };
		update_window_size(this);		
    this.$nextTick(() => {
      window.addEventListener('resize', () => {update_window_size(this);});
    });
		this.refreshDashQPS();
	},
	
  methods: {	
    changeTab: function(tab){
      history.pushState(null, null, '#i2r/'+tab);
			this.cfgTab=tab;
//      if (this.$refs.i2r.$children[tab].$attrs.table) this.$root.$emit('bv::refresh::table', this.$refs.i2r.$children[tab].$attrs.table);
    },

    get_tables (obj) {
			let doc=this;
			let URL=obj.apiUrl;
      let promise = axios.get(obj.apiUrl+'&sortBy='+obj.sortBy+'&sortDesc='+obj.sortDesc)
      return promise.then((data) => {
        items = data.data.data;
				//items[0].rowid items[items.length-1].rowid
				if (/DOCTYPE html/.test(items))	window.location.reload(false);
				if (/=hits_raw/.test(URL)) doc.hits_nrows=parseInt(data.data.records);
				if (/=queries_raw/.test(URL)) doc.qlogs_nrows=parseInt(data.data.records);
				return(items);
      }).catch(error => {
				if (/=hits_raw/.test(URL)) doc.hits_nrows=0;
				if (/=queries_raw/.test(URL)) doc.qlogs_nrows=0;
        return []
      })
    },
		
    refreshTbl(tbl){
			if ((tbl=='qlogs'||tbl=='hits') && Date.now()-this.logs_updatetime>60*1000) { //update once a minute 
	      this.$root.$emit('bv::refresh::table', tbl);
				this.logs_updatetime=Date.now();
			}else{
	      this.$root.$emit('bv::refresh::table', tbl);				
			};
    },
		
    refreshDashQPS(){
			let doc=this;
			axios.get('/rpi_admin/rpidata.php?req=qps_chart&period='+this.dash_period).then((data) => {doc.$root.qps_series=data.data});
		},

    refreshDash(){
			this.refreshDashQPS();
			this.$root.$emit('bv::refresh::table', 'dash_topX_req');
			this.$root.$emit('bv::refresh::table', 'dash_topX_req_type');
			this.$root.$emit('bv::refresh::table', 'dash_topX_client');
			this.$root.$emit('bv::refresh::table', 'dash_topX_breq');
			this.$root.$emit('bv::refresh::table', 'dash_topX_bclient');
			this.$root.$emit('bv::refresh::table', 'dash_topX_feeds');
		},
		
		//dashDrilldown(tab,item){
		//	this.qlogs_Filter=item.name;
		//	this.cfgTab=tab;
		//},
		
	},
	
});

function update_window_size(obj){
	if (obj.$refs  === undefined) {obj = io2c_app;};
	obj.logs_pp = window.innerHeight>500 && window.innerWidth>1000?Math.floor((window.innerHeight -350)/28):5;
	obj.logs_height = window.innerHeight>400?(window.innerHeight - 240):150; //250
	obj.windowInnerWidth = window.innerWidth;	
};