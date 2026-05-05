<template>
<div class="dropdown">
           <div class="topbar-item" data-toggle="dropdown" data-offset="10px,0px">
	        			<div class="btn btn-icon btn-hover-transparent-white btn-dropdown btn-lg mr-1 pulse pulse-primary">
						
           <div class="icon-notification-wrapper">
    <span class="svg-icon svg-icon-xl">
      <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
        <title>Stockholm-icons / Code / Compiling</title>
        <desc>Created with Sketch.</desc>
        <defs></defs>
        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
          <rect x="0" y="0" width="24" height="24"></rect>
          <path d="M2.56066017,10.6819805 L4.68198052,8.56066017 C5.26776695,7.97487373 6.21751442,7.97487373 6.80330086,8.56066017 L8.9246212,10.6819805 C9.51040764,11.267767 9.51040764,12.2175144 8.9246212,12.8033009 L6.80330086,14.9246212 C6.21751442,15.5104076 5.26776695,15.5104076 4.68198052,14.9246212 L2.56066017,12.8033009 C1.97487373,12.2175144 1.97487373,11.267767 2.56066017,10.6819805 Z M14.5606602,10.6819805 L16.6819805,8.56066017 C17.267767,7.97487373 18.2175144,7.97487373 18.8033009,8.56066017 L20.9246212,10.6819805 C21.5104076,11.267767 21.5104076,12.2175144 20.9246212,12.8033009 L18.8033009,14.9246212 C18.2175144,15.5104076 17.267767,15.5104076 16.6819805,14.9246212 L14.5606602,12.8033009 C13.9748737,12.2175144 13.9748737,11.267767 14.5606602,10.6819805 Z" fill="#000000" opacity="0.3"></path>
          <path d="M8.56066017,16.6819805 L10.6819805,14.5606602 C11.267767,13.9748737 12.2175144,13.9748737 12.8033009,14.5606602 L14.9246212,16.6819805 C15.5104076,17.267767 15.5104076,18.2175144 14.9246212,18.8033009 L12.8033009,20.9246212 C12.2175144,21.5104076 11.267767,21.5104076 10.6819805,20.9246212 L8.56066017,18.8033009 C7.97487373,18.2175144 7.97487373,17.267767 8.56066017,16.6819805 Z M8.56066017,4.68198052 L10.6819805,2.56066017 C11.267767,1.97487373 12.2175144,1.97487373 12.8033009,2.56066017 L14.9246212,4.68198052 C15.5104076,5.26776695 15.5104076,6.21751442 14.9246212,6.80330086 L12.8033009,8.9246212 C12.2175144,9.51040764 11.267767,9.51040764 10.6819805,8.9246212 L8.56066017,6.80330086 C7.97487373,6.21751442 7.97487373,5.26776695 8.56066017,4.68198052 Z" fill="#000000"></path>
        </g>
      </svg>
    </span>
    <span
      class="btn btn-text btn-success btn-sm font-weight-bold btn-font-md ml-1 notification-count"
      v-show="unreadnotifications.length > 0"
      style="color:black; background-color:read;"
    >
      {{ unreadnotifications.length }}
    </span>
  </div>                      

<span class="pulse-ring"></span>
	        			</div>
	                </div>

              <!--begin::Dropdown-->
	    			<div class="dropdown-menu p-0 m-0 dropdown-menu-right dropdown-menu-anim-up dropdown-menu-lg">
	                    <form>
	    	                <!--begin::Header-->


<!--begin::Content-->
 <a  target="_blank" class="dropdown-item"  :href="`${baseUrl}/tickets/myTickets`"
      >Go To my Tickets</a>

      <a class="dropdown-item" v-for="(unread, index) in unreadnotifications" :key="index">
        <h5>{{unread.data.notifiable_id}}</h5>
        <a  target="_blank"  class="dropdown-item" v-if="unread && unread.data && unread.data.ticket_id" :href="`${baseUrl}/tickets/show_ticket/${unread.data.ticket_id}`"
       @click="markAsRead(unread.data.ticket_id)"
        >Ticket  Number#{{ unread.data.ticket_id }}  assigned </a>
      </a>
      <div class="dropdown-item" v-show="unreadnotifications.length == 0">No new notifications</div>
<!--end::Content-->
	    	            </form>
	    			</div>
	                <!--end::Dropdown-->
                    	</div>
 
</template>

<script>
import axios from 'axios';

export default {
  props: {
    baseUrl: {
      type: String,
      required: true
    },
    tickets: {
      type: Number,
      default: 0
    },
    interval: {
      type: Number,
      default: 500 // Default value for interval
    }
  },
  data() {
    return {
      unreadnotifications: [],
      hasNotification: false,
      intervalId: null,
      unread: {} // Initialize unread as an empty object
    };
  },
  computed: {
    lastUnreadNotification() {
      // Filter the unread notifications array to get only the unread ones
      const unread = this.unreadnotifications.filter(
        notification => !notification.read
      );

      // Sort the filtered unread notifications by date in descending order
      unread.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

      // Return the first (last in time) unread notification
      return unread.length > 0 ? unread[0] : null;
    }
  },
  mounted() {
    this.getNotifications();
    this.intervalId = setInterval(this.getNotifications, this.interval);
  },
  beforeDestroy() {
    clearInterval(this.intervalId);
  },
  methods: {
    getNotifications() {
      axios.get(`${this.baseUrl}/unreadNotifications`)
        .then((response) => {
          this.unreadnotifications = response.data;
        })
        .catch((errors) => {
          console.log(errors);
        });
    },
      markAsRead(ticketId) {
      
      axios.get(`${this.baseUrl}/markAsRead/${ticketId}`)
        .then((response) => {
                

        })
        .catch((errors) => {
          console.log(errors);
        });
    }
  }
};
</script>

<style scoped>
.icon-notification-wrapper {
  position: relative;
  display: inline-block;
}

.notification-count {
  position: absolute;
  top: -5px; /* Adjust as needed */
  right: -5px; /* Adjust as needed */
  background-color: red; /* Background color to make it distinct */
  color: white; /* Text color */
  border-radius: 50%;
  padding: 2px 6px;
  font-size: 0.75rem; /* Adjust as needed */
}
</style>