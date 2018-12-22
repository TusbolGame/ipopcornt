<template>
    <div>
        <div class="report-modal" v-if="show_report">
            <div class="col-12 col-sm-8 col-lg-6 offset-sm-2 offset-lg-3" id="modal">
                <div class="header">
                    <span id="close" @click="CLOSE_REPORT()">
                      <svg version="1.1" class="sm-exit-svg" width="100%" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                     viewBox="0 0 511.999 511.999" style="enable-background:new 0 0 511.999 511.999;" xml:space="preserve">
                        <circle style="fill:#E21B1B;" cx="255.999" cy="255.999" r="255.999"/>
                        <g>
                                <rect x="244.002" y="120.008" transform="matrix(0.7071 -0.7071 0.7071 0.7071 -106.0397 256.0022)" style="fill:#FFFFFF;" width="24" height="271.988"/>
                                <rect x="120.008" y="244.007" transform="matrix(0.7071 -0.7071 0.7071 0.7071 -106.0428 256.0035)" style="fill:#FFFFFF;" width="271.988" height="24"/>
                        </g>
                </svg>                    </span>
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
                            <h4>{{$t('report.caption_problem')}}</h4>
                        </label>
                    </div>
                    <span class="help is-danger" v-show="errors.has('radio_group_1')">Please Choose The Problem</span>

                    <div class="textarea-details">
                        <h3>{{$t('report.more_details')}}</h3>
                        <textarea v-model="report_details" :placeholder="$t('report.more_details')" cols="40" rows="6"></textarea>
                    </div>
                    <div class="my-2">
                        <button v-if="! report_button" class="btn btn-warning" @click="SEND_REPORT">{{$t('report.send')}}</button>
                            <button class="btn btn-warning" v-if="report_button"><i id="btn-progress"></i> Loading</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- REPORT -->

        <div class="col-12 row" style="padding:0; margin: 0; height: 100%;">
            <div class="col-12">
                <div id="flowplayer-player" class="is-closeable">
                    <div id="my-player" class="fp-slim fp-outlined flowplayer">

                     <div id="flowplayer-next-video" class="col-12">
                     </div>

                     <div id="flowplayer-report-button">
                         <div class="icon-report"></div>
                     </div>

                     <div id="flowplayer-back-button">
                         <div class="icon-back"></div>
                     </div>

                    </div>

                </div>
            </div>
        </div>
    </div>
</template>
<script>

    const alertify = require('alertify.js');

    import {
        mapState
    } from 'vuex';

    export default {
        name: 'movie-player',
        data() {
            return {
                movie_title: '',
                active: '',
                show_suggestion: false,
                show_report_modal: false,
                report_problem_type: null,
                report_details: null,
                show_report_body: true,
                report_button: false,
            }
        },

        computed: mapState({
            url: state => state.player.url,
            suggestion: state => state.player.suggestion,
            show_report: state => state.player.show_report,
            next: state => state.player.next,

        }),

        mounted() {
            this.$store.dispatch('LOAD_MOVIE_PLAYER', {id: this.$route.params.id, lg_backdrop: this.lg_backdrop, md_backdrop: this.md_backdrop});
        },

        beforeRouteLeave(to, from, next) {
            this.$store.commit('FLOWPLAYER_DESTORY', 'movie');
            next();
        },

        watch: {
            next(val) {
                if (val === 'movie') {
                    this.$store.commit('FLOWPLAYER_DESTORY', 'movie');

                    if(this.suggestion.player !== 'embed') {
                        this.$store.dispatch('LOAD_MOVIE_PLAYER', {id: this.suggestion.id, lg_backdrop: this.lg_backdrop, md_backdrop: this.md_backdrop});

                    }else {
                        this.$router.push({name: 'movie-player-embed', params: {id: this.suggestion.id }})
                    }
                }
            },
        },
        methods: {
            // Change movie
            CHANGE_MOVIE(id) {
                this.$store.dispatch('LOAD_MOVIE_PLAYER', {id, lg_backdrop: this.lg_backdrop, md_backdrop: this.md_backdrop});
            },

            SEND_REPORT() {
                this.$validator.validateAll().then((result) => {
                    if (result) {
                        this.report_button = true;
                        axios.post('/api/v1/create/report/movie', {
                            type: this.report_problem_type,
                            details: this.report_details,
                            id: this.$route.params.id
                        }).then((res) => {
                            if (res.data.status === 'success') {
                                this.report_button = false;
                                this.CLOSE_REPORT();
                                alertify.logPosition("top right");
                                alertify.success('Successful Send, our team will check it soon');
                            }
                        }, (error) => {
                           this.report_button = false;
                        })
                    }
                })
            },

            // When Colse video re-play video 
            CLOSE_REPORT() {
                this.$store.commit('CLOSE_REPORT')
            },
        },
    }
</script>