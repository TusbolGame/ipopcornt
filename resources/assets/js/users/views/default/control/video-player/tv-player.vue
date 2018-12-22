<template>
    <div class="tv-player">
        <div class="report-modal" v-if="show_report">
            <div class="col-12 col-sm-8 col-lg-6 offset-sm-2 offset-lg-3" id="modal">
                <div class="header">
                    <span id="close" @click="CLOSE_REPORT()">
                        <svg version="1.1" class="sm-exit-svg" width="100%" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                            x="0px" y="0px" viewBox="0 0 511.999 511.999" style="enable-background:new 0 0 511.999 511.999;"
                            xml:space="preserve">
                            <circle style="fill:#E21B1B;" cx="255.999" cy="255.999" r="255.999" />
                            <g>
                                <rect x="244.002" y="120.008" transform="matrix(0.7071 -0.7071 0.7071 0.7071 -106.0397 256.0022)" style="fill:#FFFFFF;" width="24"
                                    height="271.988" />
                                <rect x="120.008" y="244.007" transform="matrix(0.7071 -0.7071 0.7071 0.7071 -106.0428 256.0035)" style="fill:#FFFFFF;" width="271.988"
                                    height="24" />
                            </g>
                        </svg>
                    </span>
                </div>
                <div class="body">
                    <h1>{{$t('report.what_happening')}}
                        <b style="color:dodgerblue">?</b>
                    </h1>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="custom-control-input" name="radio_group_1" v-validate="'required|in:1,2,3,4'" type="radio" value="1" v-model="report_problem_type">
                            <span class="custom-control-indicator"></span>
                            <h4>{{$t('report.labeling_problem')}}</h4>
                        </label>
                    </div>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="custom-control-input" name="radio_group_1" type="radio" value="2" v-model="report_problem_type">
                            <span class="custom-control-indicator"></span>
                            <h4>{{$t('report.video_problem')}}</h4>
                        </label>
                    </div>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="custom-control-input" name="radio_group_1" type="radio" value="3" v-model="report_problem_type">
                            <span class="custom-control-indicator"></span>
                            <h4>{{$t('report.sound_problem')}}</h4>
                        </label>
                    </div>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="custom-control-input" name="radio_group_1" type="radio" value="4" v-model="report_problem_type">
                            <span class="custom-control-indicator"></span>
                            <h4>{{$t('report.sound_problem')}}</h4>
                        </label>
                    </div>
                    <span class="help is-danger" v-show="errors.has('radio_group_1')">Please Choose The Problem</span>

                    <div class="textarea-details">
                        <h2>{{$t('report.more_details')}}</h2>
                        <textarea v-model="report_details" :placeholder="$t('report.more_details')" cols="40" rows="6"></textarea>
                    </div>
                    <div class="my-2">
                        <button v-if="! report_button" class="btn btn-warning" @click="SEND_REPORT">{{$t('report.send')}}</button>
                        <button class="btn btn-warning" v-if="report_button">
                            <i id="btn-progress"></i> Loading</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- REPORT -->

        <div class="col-12 tv-player" style="margin: 0; height: 100%;">
            <div class="row">


                <div class="col-md-4 col-lg-3 col-xl-2 hidden-sm-down side-bar">
                    <div class="col-12 p-2">
                        <div class="exit">
                            <router-link :to="{name: 'channels'}">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 52 52"
                                    style="enable-background:new 0 0 52 52;" xml:space="preserve" width="25px">
                                    <g>
                                        <path d="M26,0C11.664,0,0,11.663,0,26s11.664,26,26,26s26-11.663,26-26S40.336,0,26,0z M26,50C12.767,50,2,39.233,2,26   S12.767,2,26,2s24,10.767,24,24S39.233,50,26,50z"
                                            fill="#fff" />
                                        <path d="M35.707,16.293c-0.391-0.391-1.023-0.391-1.414,0L26,24.586l-8.293-8.293c-0.391-0.391-1.023-0.391-1.414,0   s-0.391,1.023,0,1.414L24.586,26l-8.293,8.293c-0.391,0.391-0.391,1.023,0,1.414C16.488,35.902,16.744,36,17,36   s0.512-0.098,0.707-0.293L26,27.414l8.293,8.293C34.488,35.902,34.744,36,35,36s0.512-0.098,0.707-0.293   c0.391-0.391,0.391-1.023,0-1.414L27.414,26l8.293-8.293C36.098,17.316,36.098,16.684,35.707,16.293z"
                                            fill="#fff" />
                                    </g>
                                </svg>
                            </router-link>
                        </div>

                        <div class="search mt-2">
                            <div class="search-form">
                                <input class="form-control form-control-sm" type="text" placeholder="search" v-model="searchQuery">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 channel-list">
                        <div class="loader" v-if="searchLoading">
                            <loader></loader>
                        </div>
                        <!-- Spinner Loading -->

                        <div class="channel" v-for="(item, index) in data.channel_list" :key="index" :class="{active: item.id == data.video[0].id}" @click="CHANGE_CHANNEL(item.id)" v-if="searchQuery.length <= 0">
                            <div class="row">
                                <div class="col-3 log">
                                    <img :src="'/storage/posters/' + item.image" :alt="item.name" width="100%">
                                </div>

                                <div class="col-9 title">
                                    {{item.name}}
                                </div>
                            </div>
                        </div>

                        <div class="search-result">
                            <div class="channel" v-for="(item, index) in search_data.channel_list" :key="index" :class="{active: item.id == data.video[0].id}" @click="CHANGE_CHANNEL(item.id)" v-if="search_data.channel_list.length > 0">
                                <div class="row">
                                    <div class="col-3 log">
                                        <img :src="'/storage/posters/' + item.image" :alt="item.name" width="100%">
                                    </div>

                                    <div class="col-9 title">
                                        {{item.name}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-8 col-lg-9 col-xl-10 p-md-0 player">
                    <div class="col-12">
                        <div id="flowplayer-player" class="is-closeable">
                            <div id="my-player" class="fp-slim fp-outlined flowplayer">



                                <div id="flowplayer-report-button">
                                    <div class="icon-report"></div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script>

    const alertify = require("alertify.js");
    import {
        mapState
    } from "vuex";
    import loader from '../utils/loader'
    export default {
        name: "tv-player",
        data() {
            return {
                movie_title: "",
                active: "",
                show_suggestion: false,
                show_report_modal: false,
                report_problem_type: null,
                report_details: null,
                show_report_body: true,
                report_button: false,
                searchQuery: '',
            };
        },

        components: {
            loader
        },
        computed: mapState({
            data: state => state.player.data,
            url: state => state.player.url,
            show_report: state => state.player.show_report,
            search_data: state => state.tv.search_data,
            searchLoading: state => state.tv.searchLoading
        }),


        beforeRouteLeave(to, from, next) {
            flowplayer(0).engine.unload();
            next();
        },


        mounted() {
           //  var flowplayerhljslive = document.createElement('script');
           //
           //  flowplayerhljslive.setAttribute('src','//releases.flowplayer.org/hlsjs/flowplayer.hlsjs.light.min.js');
           //
           // var a = document.head.appendChild(flowplayerhljslive);
           //
           // console.log(a);

            setTimeout( () =>{
                this.$store.dispatch("LOAD_TV", this.$route.params.id);
            },200)
        },

        watch: {
            searchQuery(query) {
                if(query.length > 0 ) {
                    this.searchLoading = true;
                    this.$store.dispatch("LOAD_SEARCH_TV_LIST", query);
                }
            },
        },
        methods: {
            SEND_REPORT() {
                this.$validator.validateAll().then(result => {
                    if (result) {
                        this.report_button = true;
                        axios
                            .post("/api/v1/create/report/movie", {
                                type: this.report_problem_type,
                                details: this.report_details,
                                id: this.$route.params.id
                            })
                            .then(
                                res => {
                                    if (res.data.status === "success") {
                                        this.show_report_body = false;
                                        this.report_button = false;
                                        setTimeout(() => {
                                            this.show_report_body = true;
                                        }, 1000);
                                        alertify.logPosition("top right");
                                        alertify.success(
                                            "Successful Send, our team will check it soon"
                                        );
                                    }
                                },
                                error => {
                                    this.report_button = false;
                                }
                            );
                    }
                });
            },

            // Exit
            EXIT() {
                this.$router.go(-1);
            },

            CLOSE_REPORT() {
                this.$store.commit("CLOSE_REPORT");
            },

            CHANGE_CHANNEL(id) {
                this.$store.commit('FLOWPLAYER_DESTORY', 'tv');
                this.$store.dispatch("LOAD_TV", id);
            }
        }
    };
</script>
