import Vue from 'vue';
import router from '../../packages/Routes';
import {
    stat
} from 'fs';
const module = {
    state: {
        data: [],
        season: [],
        suggestion: [],
        bookmark: [],
        url: [],
        show_report: false,
        next: null,
        next_episode: null,
        next_season: null,
        next_is: null,
        next_playlist: null,
        next_episode_playlist: null,
        season_playlist_active: null

        /* TV */

    },
    actions: {

        /**
         * Get movie vidoe,subtitle,suggestion
         *
         * @param {any} param
         * @param {any} id
         */

        LOAD_MOVIE_PLAYER({commit}, {id, lg_backdrop, md_backdrop}) {
            axios.get('/api/v1/get/watch/movie/' + id).then(response => {
                if (response.status === 200) {
                    commit('SET_MOVIE', {data:response.data.data, lg_backdrop, md_backdrop});
                }
            }, error => {
                // Show Sweetalert if there is problem
                swal({
                    icon: 'error',
                    title: 'There was a problem playing the video, we will fix it soon',
                    dangerMode: true,
                    button: 'Back',
                }).then(() => {
                    window.history.back();
                    videojs.dispose();
                });
            });
        },


        /**
         *
         * @param {*}  commit
         * @param {uuid,string,uuid}  Array
         */
        LOAD_SERIES_PLAYER({commit}, {episode_id, type, series_id, lg_backdrop,md_backdrop}) {
            axios.post('/api/v1/get/watch/series', {
                episode_id: episode_id,
                type: type,
                series_id: series_id,
            })
                .then(response => {
                    if (response.data.status === 'success') {
                        commit('SET_SERIES', {data:response.data.data, lg_backdrop, md_backdrop});
                    }
                }, error => {
                    swal({
                        icon: 'error',
                        title: 'There was a problem playing the video, we will fix it soon',
                        dangerMode: true,
                        button: 'Back',
                    }).then(() => {
                        window.history.back();
                        videojs.dispose();
                    });
                });
        },

        /**
         *  Tv
         *
         * @param commit
         * @param id
         * @constructor
         */
        LOAD_TV({commit, dispatch}, id) {
            axios.get('/api/v1/get/watch/tv/' + id)
                .then((res) => {
                    commit('SET_DATA_PLAYER_TV',  res.data.data);
                });
        },

    },
    mutations: {

        /**
         * This mutations to set all video details (video resolation,subtitle,suggestion) in videojs player
         *
         * @param {*} state
         * @param {*} data
         */

        SET_MOVIE(state, data) {
            state.next = null;
            state.suggestion = data.data.suggestion;

            // // Check if there is custom caption settting or not
            // // If there is custom setting add stylesheet
            if (localStorage.getItem('caption') !== "" && localStorage.getItem('caption') != "undefined") {

                const parsedCaption = JSON.parse(localStorage.getItem('caption'));
                let css;
                if (parsedCaption !== null && parsedCaption !== 'null' && parsedCaption !== undefined) {
                    css = ` .flowplayer .fp-captions p {
                                    color:` + parsedCaption['color'] + ` !important;
                                    background-color: ` + parsedCaption['background-color'] + ` !important;
                                    font-size: ` + parseInt(parsedCaption['font-size']) * 2 + `px;
                                    font-weight: ` + parsedCaption['font-weight'] + `
                                   }`;

                } else {
                    css = `.flowplayer .fp-captions p {
                                    color:#fff !important;
                                    background-color:  transparent !important;
                                    font-size: 35px;
                                    font-weight: 600;
                                   }`;
                }
                const head = document.head || document.getElementsByTagName('head')[0];
                const style = document.createElement('style');

                style.type = 'text/css';
                if (style.styleSheet) {
                    style.styleSheet.cssText = css;
                } else {
                    style.appendChild(document.createTextNode(css));
                }

                head.appendChild(style);
            }
            else {
                let css = ` .flowplayer .fp-captions p {
                                    color:#fff !important;
                                    background-color:  transparent !important;
                                    font-size: 35px;
                                    font-weight: 600;
                                   }`;
                const head = document.head || document.getElementsByTagName('head')[0];
                const style = document.createElement('style');

                style.type = 'text/css';
                if (style.styleSheet) {
                    style.styleSheet.cssText = css;
                } else {
                    style.appendChild(document.createTextNode(css));
                }

                head.appendChild(style);
            }


            /***********************************/

            // Add Component Before Init Player

            /***********************************/


            // Add Report Icon To Player
            flowplayer(function (api, root) {
                var reportButton = document.getElementById("flowplayer-report-button");
                var backButton = document.getElementById("flowplayer-back-button");

                // append fullscreen button after HD menu is added on ready
                api.on("ready", function () {
                    root.querySelector(".fp-header").appendChild(reportButton);
                    root.querySelector(".fp-header").appendChild(backButton);
                });
            });


            /***********************************/

            // Init Player & Set Video and Subtitles

            /***********************************/

            // Get Element of player div
            let container = document.getElementById('my-player');


            // Import subtitle to array
            let TextTrack = [];
            if (data.data.subtitle !== null) {
                for (var i = 0; i < data.data.subtitle.length; i++) {
                    TextTrack.push(
                        {
                            kind: 'subtitles',
                            srclang: 'en',
                            label: data.data.subtitle[i].subtitle_language,
                            src: data.data.subtitle[i].subtitle_name
                        }
                    );
                }
            }

            // Check image backdrop
            let md_backdrop, lg_backdrop;

            if(data.data.video[0].cloud == 'local'){
                 lg_backdrop = '/storage/backdrops/original_';
            }else {
                 lg_backdrop = data.lg_backdrop;
            }


            // Check and Sort Vidoe Link
            let fplayer;
            let VideoList = [];
            let QualitieList = [];
            if (data.data.video_format == 'm3u8') {
                VideoList.push(
                    {
                        type: 'application/x-mpegurl',
                        src: data.data.video[0].video,
                    }
                );

                // SetUP Player
                fplayer = flowplayer(container, {
                    hlsjs: {
                        xhrSetup: function(xhr) {
                            xhr.withCredentials = true; // do send cookies
                        }
                    },
                    autoplay: true,
                    aspectRatio: "16:9",
                    speeds: [0.25, 0.5, 1, 1.5, 2], // default
                    customPlaylist: true,
                    poster: lg_backdrop + data.data.video[0].backdrop,
                    share: false,
                    chromecast: true,
                    clip: {
                        hlsjs: {
                            safari: true
                        },
                        subtitles: TextTrack,
                        sources: VideoList,
                    },
                    embed: false
                });


            } else if (data.data.video_format == 'mp4') {


                for (var i = 0; i < data.data.video.length; i++) {
                    VideoList.push(
                        {
                            type: 'video/mp4',
                            src: data.data.video[i].video,
                        }
                    );
                }

                for (var i = 0; i < data.data.video.length; i++) {
                    QualitieList.push({
                        label: data.data.video[i].resolution + 'p',
                        src: data.data.video[i].video
                    });
                }


                // SetUP Player
                fplayer = flowplayer(container, {
                    hlsjs: {
                        xhrSetup: function(xhr) {
                            xhr.withCredentials = true; // do send cookies
                        }
                    },
                    autoplay: true,
                    aspectRatio: "16:9",
                    customPlaylist: true,
                    poster: lg_backdrop + data.data.video[0].backdrop,
                    share: false,
                    chromecast: true,
                    clip: {
                        title: data.data.video[0].name,
                        subtitles: TextTrack,
                        sources: VideoList,
                        qualities: QualitieList,
                        defaultQuality: QualitieList[0].label
                    }
                });


            }


            /***********************************/

            // 1- Send request recently time of video
            // 2- Check next movie

            /***********************************/


            let RecentlyTime = 200; // Default second


            // Change RecenltyTime in Seeking

            fplayer.on('seek', (e, api) => {
                RecentlyTime = api.video.time.toFixed();
            });


            // Check Recently
            fplayer.on('ready', (e, api) => {
                if (data.data.video[0].current_time !== null) {
                    api.seek(data.data.video[0].current_time);
                    RecentlyTime = data.data.video[0].current_time + 200;
                }
            });


            // Send Recently Request
            fplayer.on('progress', (e, api) => {


                // Set recently time
                if (api.video.time.toFixed() == RecentlyTime) {

                    // Plus 5 second
                    RecentlyTime = parseInt(api.video.time.toFixed()) + 200;

                    // Send request
                    axios.post('/api/v1/create/watch/movie/recently', {
                        current_time: api.video.time.toFixed(),
                        duration_time: api.video.duration.toFixed(),
                        movie_id: data.data.video[0].id,
                    });
                }


                // Show Next Moive before 20 Second
                if (parseInt(api.video.duration.toFixed()) - 20 <= api.video.time.toFixed()) {
                    var showElement = document.querySelector('.hide-play-next-movie'); // bug
                    if (showElement !== null) {
                        showElement.classList.remove('hide-play-next-movie');
                        showElement.classList.add('show-play-next-movie');

                    }
                } else {
                    var hideElement = document.querySelector('.show-play-next-movie'); // bug
                    if (hideElement !== null) {
                        hideElement.classList.add('hide-play-next-movie');
                        hideElement.classList.remove('show-play-next-movie');
                    }
                }


                // auto play next movie
                if (parseInt(api.video.duration.toFixed()) - 4 == api.video.time.toFixed()) {
                    if (data.data.suggestion !== null) {
                        state.next = 'movie';
                    }
                }

            });


            // Add Next Movie Player
            if (data.data.suggestion !== null) {

                if(data.data.suggestion.cloud == 'local'){
                    md_backdrop = '/storage/backdrops/300_';

                }else {
                    md_backdrop = data.md_backdrop;
                }


                var element = document.getElementById('flowplayer-next-video'); // bugs
                element.innerHTML = `
                    <div class="col-12 col-sm-4 col-xl-3 content hide-play-next-movie">
                    
                      <div class="title">
                        <p> <span>NEXT</span> ` + data.data.suggestion.name + `</p> 
                     </div>
                     
                     <div class="backdrop">
                          <img src="` +  md_backdrop + data.data.suggestion.backdrop + `" width="100%">
                     </div>
                     
                     <div class="play-next-movie-button">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 294.843 294.843" style="enable-background:new 0 0 294.843 294.843;" xml:space="preserve" width="100%" id="play-next-movie" class="play-svg"> <g> <g> <path d="M278.527,79.946c-10.324-20.023-25.38-37.704-43.538-51.132c-2.665-1.97-6.421-1.407-8.392,1.257s-1.407,6.421,1.257,8.392 c16.687,12.34,30.521,28.586,40.008,46.983c9.94,19.277,14.98,40.128,14.98,61.976c0,74.671-60.75,135.421-135.421,135.421 S12,222.093,12,147.421S72.75,12,147.421,12c3.313,0,6-2.687,6-6s-2.687-6-6-6C66.133,0,0,66.133,0,147.421 s66.133,147.421,147.421,147.421s147.421-66.133,147.421-147.421C294.842,123.977,289.201,100.645,278.527,79.946z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> <path d="M109.699,78.969c-1.876,1.067-3.035,3.059-3.035,5.216v131.674c0,3.314,2.687,6,6,6s6-2.686,6-6V94.74l88.833,52.883 l-65.324,42.087c-2.785,1.795-3.589,5.508-1.794,8.293c1.796,2.786,5.508,3.59,8.294,1.794l73.465-47.333 c1.746-1.125,2.786-3.073,2.749-5.15c-0.037-2.077-1.145-3.987-2.93-5.05L115.733,79.029 C113.877,77.926,111.575,77.902,109.699,78.969z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> </g> </g> </svg>
                     </div>
                     
                    </div>
                `;


                const nextMovieButton = document.getElementById('play-next-movie');

                nextMovieButton.addEventListener('click', () => {
                    state.next = 'movie';
                });


            }


            // Report Event Listener

            const reportButton = document.getElementById('flowplayer-report-button');

            reportButton.addEventListener('click', () => {
                state.show_report = true;
                fplayer.pause();
            });


            // Back Event Listener

            const backButton = document.getElementById('flowplayer-back-button');

            backButton.addEventListener('click', () => {
                fplayer.stop();
                router.push({name: 'movies'});
            });

        },


        /**
         *
         * @param {*} state
         * @param {*} list
         */
        SET_SERIES(state, data) {
            state.data = data.data.episode[0];
            state.season = data.data.season;
            state.next_season = null;
            state.next_episode = null;
            state.next = null;
            state.next_is = null;
            state.next_playlist = null;
            state.season_playlist_active = data.data.episode[0].season_number;

            // // Check if there is custom caption settting or not
            // // If there is custom setting add stylesheet
            if (localStorage.getItem('caption') !== "" && localStorage.getItem('caption') != "undefined") {

                const parsedCaption = JSON.parse(localStorage.getItem('caption'));
                let css;
                if (parsedCaption !== null && parsedCaption !== 'null' && parsedCaption !== undefined) {
                    css = `.flowplayer .fp-captions p {
                                    color:` + parsedCaption['color'] + ` !important;
                                    background-color: ` + parsedCaption['background-color'] + ` !important;
                                    font-size: ` + parseInt(parsedCaption['font-size']) * 2 + `px;
                                    font-weight: ` + parsedCaption['font-weight'] + `
                                   }`;

                } else {
                    css = `.flowplayer .fp-captions p {
                                    color:#fff !important;
                                    background-color:  transparent !important;
                                    font-size: 35px;
                                    font-weight: 600;
                                   }`;
                }
                const head = document.head || document.getElementsByTagName('head')[0];
                const style = document.createElement('style');

                style.type = 'text/css';
                if (style.styleSheet) {
                    style.styleSheet.cssText = css;
                } else {
                    style.appendChild(document.createTextNode(css));
                }

                head.appendChild(style);
            }
            else {
                let css = `.flowplayer .fp-captions p{
                                    color:#fff !important;
                                    background-color:  transparent !important;
                                    font-size: 35px;
                                    font-weight: 600;
                                   }`;
                const head = document.head || document.getElementsByTagName('head')[0];
                const style = document.createElement('style');

                style.type = 'text/css';
                if (style.styleSheet) {
                    style.styleSheet.cssText = css;
                } else {
                    style.appendChild(document.createTextNode(css));
                }

                head.appendChild(style);
            }


            /***********************************/

            // Add Component Before Init Player

            /***********************************/


            flowplayer(function (api, root) {

                // Append report button
                // Append playlist season
                var reportButton = document.getElementById("flowplayer-report-button");
                var playlistComponent = document.getElementById("flowplayer-playlist");
                var playlistButton = document.getElementById("flowplayer-playlist-button");
                var backButton = document.getElementById("flowplayer-back-button");

                api.on("ready", function () {
                    root.querySelector(".fp-header").appendChild(reportButton);
                    root.querySelector(".fp-ui").appendChild(playlistComponent);
                    root.querySelector(".fp-header").appendChild(playlistButton);
                    root.querySelector(".fp-header").appendChild(backButton);

                });

            });


            /***********************************/

            // Init Player & Set Video and Subtitles

            /***********************************/

            // Get Element of player div

            let container = document.getElementById('my-player');


            // Import subtitle to array
            let TextTrack = [];
            if (data.data.subtitle !== null) {
                for (var i = 0; i < data.data.subtitle.length; i++) {
                    TextTrack.push(
                        {
                            kind: 'subtitles',
                            srclang: 'en',
                            label: data.data.subtitle[i].subtitle_language,
                            src: data.data.subtitle[i].subtitle_name
                        }
                    );
                }
            }


            // Check image backdrop
            let md_backdrop, lg_backdrop;

            if(data.data.episode[0].cloud == 'local'){
                lg_backdrop = '/storage/backdrops/original_';
            }else {
                lg_backdrop = data.lg_backdrop;
            }



            // Check and Sort Video Link
            let fplayer;
            let VideoList = [];
            let QualitieList = [];

            if (data.data.video_format == 'm3u8') {
                VideoList.push(
                    {
                        type: 'application/x-mpegurl',
                        src: data.data.episode[0].video
                    }
                );

                // SetUP Player
                fplayer = flowplayer(container, {
                    hlsjs: {
                        xhrSetup: function(xhr) {
                            xhr.withCredentials = true; // do send cookies
                        }
                    },
                    autoplay: true,
                    aspectRatio: "16:9",
                    customPlaylist: true,
                    poster: lg_backdrop + data.data.episode[0].backdrop,
                    share: false,
                    chromecast: true,
                    clip: {
                        title: data.data.episode[0].name,
                        hlsjs: {
                            safari: true
                        },
                        subtitles: TextTrack,
                        sources: VideoList,
                    }
                });


            }
            else if (data.data.video_format == 'mp4') {

                for (let i = 0; i < data.data.episode.length; i++) {

                    VideoList.push(
                        {
                            type: 'video/mp4',
                            src: data.data.episode[i].video,
                        }
                    );
                }

                for (let i = 0; i < data.data.episode.length; i++) {
                    QualitieList.push({
                        label: data.data.episode[i].resolution + 'p',
                        src: data.data.episode[i].video
                    });
                }


                // SetUP Player
                fplayer = flowplayer(container, {
                    hlsjs: {
                        xhrSetup: function(xhr) {
                            xhr.withCredentials = true; // do send cookies
                        }
                    },
                    autoplay: true,
                    aspectRatio: "16:9",
                    customPlaylist: true,
                    poster: lg_backdrop + data.data.episode[0].backdrop,
                    share: false,
                    chromecast: true,
                    clip: {
                        title: data.data.episode[0].name,
                        subtitles: TextTrack,
                        sources: VideoList,
                        qualities: QualitieList,
                        defaultQuality: QualitieList[0].label
                    }
                });


            }


            /***********************************/

            // 1- Send request recenlty time of video
            // 2- Check next season and episode

            /***********************************/


            let RecentlyTime = 200; // Default second


            // Change RecenltyTime in Seeking

            fplayer.on('seek', (e, api) => {
                RecentlyTime = api.video.time.toFixed();
            });


            // Check Recently
            fplayer.on('ready', (e, api) => {
                if (data.data.episode[0].current_time !== null) {
                    api.seek(data.data.episode[0].current_time);
                    RecentlyTime = data.data.episode[0].current_time + 200;
                }
            });


            // Send Recently Request
            fplayer.on('progress', (e, api) => {


                // Set recently time
                if (api.video.time.toFixed() == RecentlyTime) {

                    // Plus 5 second
                    RecentlyTime = parseInt(api.video.time.toFixed()) + 200;

                    // Send request
                    axios.post('/api/v1/create/watch/series/recently', {
                        current_time: api.video.time.toFixed(),
                        duration_time: api.video.duration.toFixed(),
                        episode_id: data.data.episode[0].id,
                        series_id: data.data.episode[0].series_id
                    });
                }


                // Show Next Moive before 20 Second
                if (parseInt(api.video.duration.toFixed()) - 20 <= api.video.time.toFixed()) {




                    // Check if there next episode
                    if (state.next_episode == null) {
                        for (let i = 0; i < data.data.season[data.data.episode[0].season_number].length; i++) {
                            if (data.data.episode[0].episode_number === data.data.season[data.data.episode[0].season_number][i].episode_number) {
                                if (data.data.season[data.data.episode[0].season_number].hasOwnProperty(i + 1)) {
                                    state.next_episode = data.data.season[data.data.episode[0].season_number][i + 1];
                                } else {
                                    state.next_episode = null;
                                }
                            }
                        }
                    }

                    // Check if there next season
                    if (state.next_season == null) {
                        if (data.data.next_season !== null) {
                            state.next_season = data.data.next_season;
                        }
                    }


                    // Add next episode template
                    if (state.next_episode !== null) {
                        let md_backdrop_next_episode;
                        if(state.next_episode.cloud == 'local'){
                            md_backdrop_next_episode = '/storage/backdrops/original_';
                        }else {
                            md_backdrop_next_episode = data.md_backdrop;
                        }


                        // Next Episode
                        var epElement = document.getElementById('flowplayer-next-video'); // bugs
                        var checkEpElement = document.getElementById('play-next-episode'); // bugs

                        if (checkEpElement == null) {

                            epElement.innerHTML = `
                            <div class="col-12 col-sm-4 col-xl-3 content hide-play-next-episode" id="play-next-episode">
                            
                              <div class="title">
                                <p> <span>PLAY NEXT EPISODE </span> S` + state.next_episode.season_number + `E` + state.next_episode.episode_number + ': ' + state.next_episode.name + `</p> 
                             </div>
                             
                             <div class="backdrop">
                                  <img src="`+ md_backdrop_next_episode + state.next_episode.backdrop + `" width="100%" >
                             </div>
                             
                             <div class="play-next-movie-button">
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 294.843 294.843" style="enable-background:new 0 0 294.843 294.843;" xml:space="preserve" width="100%" id="play-next-episode" class="play-svg"> <g> <g> <path d="M278.527,79.946c-10.324-20.023-25.38-37.704-43.538-51.132c-2.665-1.97-6.421-1.407-8.392,1.257s-1.407,6.421,1.257,8.392 c16.687,12.34,30.521,28.586,40.008,46.983c9.94,19.277,14.98,40.128,14.98,61.976c0,74.671-60.75,135.421-135.421,135.421 S12,222.093,12,147.421S72.75,12,147.421,12c3.313,0,6-2.687,6-6s-2.687-6-6-6C66.133,0,0,66.133,0,147.421 s66.133,147.421,147.421,147.421s147.421-66.133,147.421-147.421C294.842,123.977,289.201,100.645,278.527,79.946z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> <path d="M109.699,78.969c-1.876,1.067-3.035,3.059-3.035,5.216v131.674c0,3.314,2.687,6,6,6s6-2.686,6-6V94.74l88.833,52.883 l-65.324,42.087c-2.785,1.795-3.589,5.508-1.794,8.293c1.796,2.786,5.508,3.59,8.294,1.794l73.465-47.333 c1.746-1.125,2.786-3.073,2.749-5.15c-0.037-2.077-1.145-3.987-2.93-5.05L115.733,79.029 C113.877,77.926,111.575,77.902,109.699,78.969z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> </g> </g> </svg>
                             </div>
                             
                            </div>
                        `;

                            var showEpElement = document.querySelector('.hide-play-next-episode'); // bug
                            if (showEpElement !== null) {
                                showEpElement.classList.remove('hide-play-next-episode');
                                showEpElement.classList.add('show-play-next-episode');
                            }


                            // Event listener for play icon episode/season
                            const nextEpisodeButton = document.getElementById('play-next-episode');
                            if (state.next_episode !== null) {
                                nextEpisodeButton.addEventListener('click', () => {
                                    state.next_is = 'episode';
                                });
                            }

                        }


                    }
                    else if (state.next_season !== null) {

                        let md_backdrop_next_season
                        if(state.next_season.cloud == 'local'){
                            md_backdrop_next_season = '/storage/backdrops/original_';
                        }else {
                            md_backdrop_next_season = data.md_backdrop;
                        }


                        // Next Season
                        var seElement = document.getElementById('flowplayer-next-video'); // bugs
                        var checkSeElement = document.getElementById('play-next-season'); // bugs

                        if (checkSeElement == null) {

                            seElement.innerHTML = `
                            <div class="col-12 col-sm-4 col-xl-3 content hide-play-next-episode" id="play-next-season">
                            
                              <div class="title">
                                <p> <span>PLAY NEXT SEASON </span> S` + state.next_season.season_number + `E` + state.next_season.episode_number + ': ' + state.next_season.name + `</p> 
                             </div>
                             
                             <div class="backdrop">
                                  <img src="` + md_backdrop_next_season + state.next_season.backdrop + `" width="100%" >
                             </div>
                             
                             <div class="play-next-movie-button">
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 294.843 294.843" style="enable-background:new 0 0 294.843 294.843;" xml:space="preserve" width="100%" id="play-next-season" class="play-svg"> <g> <g> <path d="M278.527,79.946c-10.324-20.023-25.38-37.704-43.538-51.132c-2.665-1.97-6.421-1.407-8.392,1.257s-1.407,6.421,1.257,8.392 c16.687,12.34,30.521,28.586,40.008,46.983c9.94,19.277,14.98,40.128,14.98,61.976c0,74.671-60.75,135.421-135.421,135.421 S12,222.093,12,147.421S72.75,12,147.421,12c3.313,0,6-2.687,6-6s-2.687-6-6-6C66.133,0,0,66.133,0,147.421 s66.133,147.421,147.421,147.421s147.421-66.133,147.421-147.421C294.842,123.977,289.201,100.645,278.527,79.946z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> <path d="M109.699,78.969c-1.876,1.067-3.035,3.059-3.035,5.216v131.674c0,3.314,2.687,6,6,6s6-2.686,6-6V94.74l88.833,52.883 l-65.324,42.087c-2.785,1.795-3.589,5.508-1.794,8.293c1.796,2.786,5.508,3.59,8.294,1.794l73.465-47.333 c1.746-1.125,2.786-3.073,2.749-5.15c-0.037-2.077-1.145-3.987-2.93-5.05L115.733,79.029 C113.877,77.926,111.575,77.902,109.699,78.969z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> </g> </g> </svg>
                             </div>
                             
                            </div>
                        `;


                            var showSeElement = document.querySelector('.hide-play-next-episode'); // bug
                            if (showSeElement !== null) {
                                showSeElement.classList.remove('hide-play-next-episode');
                                showSeElement.classList.add('show-play-next-episode');
                            }

                            const nextSeasonButton = document.getElementById('play-next-season');

                            if (state.next_season !== null) {
                                nextSeasonButton.addEventListener('click', () => {
                                    state.next_is = 'season';
                                });
                            }

                        }
                    }


                } else {
                    var hideElement = document.querySelector('.show-play-next-episode'); // bug
                    if (hideElement !== null) {
                        hideElement.classList.add('hide-play-next-episode');
                        hideElement.classList.remove('show-play-next-episode');
                    }
                }


                // Auto play next movie
                if (parseInt(api.video.duration.toFixed()) - 3 <= api.video.time.toFixed()) {

                    if (state.next_episode !== null) {
                        state.next = 'episode';
                    } else if (state.next_season !== null) {
                        state.next = 'season';

                    }

                }

            });


            /***********************************/

            // Create Season Playlist

            /***********************************/


            if(data.data.season.length !== 0) {

                var seasonComponent = document.getElementById("flowplayer-playlist");


                let seasonOpened = data.data.episode[0].season_number;

                function playlistComponentRender() {
                    let seasonList = ``;
                    for (let key in state.season) {
                        if (state.season.hasOwnProperty(key)) {
                            if (seasonOpened == key) {
                                seasonList += `<button class="season-toggle-btn active noselect" id="` + key + `">` + key + `</button>`;
                            } else {
                                seasonList += `<button class="season-toggle-btn noselect" id="` + key + `">` + key + `</button>`;
                            }
                        }
                    }

                    let episodeList = ``;
                    for (let index = 0; index < state.season[seasonOpened].length; index++) {

                        if (data.data.episode[0].id == state.season[seasonOpened][index].id) {

                            let md_backdrop_playlist;
                            if(state.season[seasonOpened][index].cloud == 'local'){
                                md_backdrop_playlist = '/storage/backdrops/original_';
                            }else {
                                md_backdrop_playlist = data.md_backdrop;
                            }


                            episodeList += `<li class="current mt-2">
                                                    <div class="image">

                                                    <img src="` + md_backdrop_playlist + state.season[seasonOpened][index].backdrop + `" width="100%">

                                                    <div class="ovarlay">
                                                        <div class="number">
                                                         <p>` + state.season[seasonOpened][index].episode_number + ` </p>
                                                        </div>

                                                        <div class="play">
                                                             <h3>You watch</h3>
                                                        </div>

                                                    </div>

                                                    </div>
                                                    <div class="overview mt-1">
                                                        <strong>` + state.season[seasonOpened][index].name + `</strong>
                                                         <hr>
                                                        <p class="hidden-xs-down">` + state.season[seasonOpened][index].overview + `</p>
                                                    </div>
                                      </li>`;

                        } else {
                            let md_backdrop_playlist;
                            if(state.season[seasonOpened][index].cloud == 'local'){
                                md_backdrop_playlist = '/storage/backdrops/original_';
                            }else {
                                md_backdrop_playlist = data.md_backdrop;
                            }

                            episodeList += `<li class="mt-2">
                                                   <div class="image">

                                                    <img src="` + md_backdrop_playlist + state.season[seasonOpened][index].backdrop + `" width="100%"  id="` + state.season[seasonOpened][index].id + `">
                                                    <div class="episode" id="` + state.season[seasonOpened][index].id + `"></div>
                                                    <div class="ovarlay">

                                                     <div class="number">
                                                       <p>` + state.season[seasonOpened][index].episode_number + ` </p>
                                                     </div>

                                                     <div class="play">
                                                         <div class="playlist-play-button" id="` + state.season[seasonOpened][index].id + `">
                                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 294.843 294.843" style="enable-background:new 0 0 294.843 294.843;" xml:space="preserve" width="100%" id="` + state.season[seasonOpened][index].id + `" class="play-svg"> <g> <g> <path d="M278.527,79.946c-10.324-20.023-25.38-37.704-43.538-51.132c-2.665-1.97-6.421-1.407-8.392,1.257s-1.407,6.421,1.257,8.392 c16.687,12.34,30.521,28.586,40.008,46.983c9.94,19.277,14.98,40.128,14.98,61.976c0,74.671-60.75,135.421-135.421,135.421 S12,222.093,12,147.421S72.75,12,147.421,12c3.313,0,6-2.687,6-6s-2.687-6-6-6C66.133,0,0,66.133,0,147.421 s66.133,147.421,147.421,147.421s147.421-66.133,147.421-147.421C294.842,123.977,289.201,100.645,278.527,79.946z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> <path d="M109.699,78.969c-1.876,1.067-3.035,3.059-3.035,5.216v131.674c0,3.314,2.687,6,6,6s6-2.686,6-6V94.74l88.833,52.883 l-65.324,42.087c-2.785,1.795-3.589,5.508-1.794,8.293c1.796,2.786,5.508,3.59,8.294,1.794l73.465-47.333 c1.746-1.125,2.786-3.073,2.749-5.15c-0.037-2.077-1.145-3.987-2.93-5.05L115.733,79.029 C113.877,77.926,111.575,77.902,109.699,78.969z" data-original="#000000" class="active-path" data-old_color="#ffffff" fill="#ffffff" /> </g> </g> </svg>
                                                         </div>
                                                      </div>

                                                   </div>
                                                   </div>

                                                    <div class="overview mt-1">
                                                         <strong>` + state.season[seasonOpened][index].name + `</strong>
                                                          <hr>
                                                         <p class="hidden-xs-down" id="` + state.season[seasonOpened][index].id + `">` + state.season[seasonOpened][index].overview + `</p>
                                                    </div>
                                        </li>`;
                        }
                    }


                    // Add component
                    seasonComponent.innerHTML = `<div class="playlist-content"> <div class="season-list">` + seasonList + `</div>` + `<div class="episode-list">` + episodeList + `</div></div> <div id="toggle-playlist-button"><div class="toggle-playlist-icon"></div></div> `;

                }

                playlistComponentRender();

                // Event Listener To Change Episode

                function playEpisode(e) {
                    state.next_episode_playlist = e.target.id;
                }


                const playEpisodeButton = document.querySelectorAll('.playlist-play-button');

                for (let i = 0; i < playEpisodeButton.length; i++) {
                    playEpisodeButton[i].addEventListener('click', playEpisode, false);
                }



                // Event Listener To Change Season
                function setSeason(e) {
                    seasonOpened = e.target.id;
                    state.season_playlist_active =  e.target.id;
                    playlistComponentRender();

                    // Re-set Event Listener for season/episode
                    const seasonButton = document.getElementsByClassName('season-toggle-btn');
                    for (let i = 0; i < seasonButton.length; i++) {
                        seasonButton[i].addEventListener('click', setSeason, false);
                    }


                    const playEpisodeButton = document.getElementsByClassName('playlist-play-button');
                    for (let i = 0; i < playEpisodeButton.length; i++) {
                        playEpisodeButton[i].addEventListener('click', playEpisode, false);
                    }

                }

                const seasonButton = document.getElementsByClassName('season-toggle-btn');

                for (let i = 0; i < seasonButton.length; i++) {
                    seasonButton[i].addEventListener('click', setSeason, false);
                }


                // Event Listener Open Playlist

                const playlistButton = document.getElementById('flowplayer-playlist-button');

                playlistButton.addEventListener('click', () => {
                    seasonComponent.classList.toggle("show")
                });


            }





            // Event Listener Open Report

            const reportButton = document.getElementById('flowplayer-report-button');

            reportButton.addEventListener('click', () => {
                state.show_report = true;
            });


            // Back Event Listener

            const backButton = document.getElementById('flowplayer-back-button');

            backButton.addEventListener('click', () => {
                fplayer.stop();
                router.back(-1);
            });

        },


        SET_DATA_PLAYER_TV(state, data) {

            state.data = data;

            /***********************************/

            // Add Component Before Init Player

            /***********************************/


            // Get Element of player div
            let container = document.getElementById('my-player');



            // Add Report Icon To Player
            flowplayer(function (api, root) {
                var reportButton = document.getElementById("flowplayer-report-button");

                // append fullscreen button after HD menu is added on ready
                api.on("ready", function () {
                    root.querySelector(".fp-header").appendChild(reportButton);
                });
            });


            let fplayer;
            let VideoList;

            // If the HLS is form External Link
            if (state.data.video[0].streaming_transcoding == 1) {
                VideoList = [{
                    src: '/storage/iptv/' + state.data.video[0].id + '/' + state.data.video[0].streaming_name,
                    type: 'application/x-mpegURL',
                }];

            } else {
                VideoList = [{
                    src: state.data.video[0].streaming_url,
                    type: 'application/x-mpegURL',
                }];
            }

            // SetUP Player
            fplayer = flowplayer(container, {
                autoplay: true,
                aspectRatio: "16:9",
                customPlaylist: true,
                share: false,
                chromecast: true,
                live: true,  // set if it's a live stream
                clip: {
                    hlsjs: {
                        safari: true
                    },
                    live: true,
                    sources: VideoList,
                }
            });



        },

        CLOSE_REPORT(state) {
            state.show_report = false;
        },


        FLOWPLAYER_DESTORY(state, type) {
            // Remove Player
            var reElement = document.getElementById("my-player");
            reElement.parentNode.removeChild(reElement);

            var addElement = document.getElementById("flowplayer-player");

            if(type == 'movie') {
                // Add new player
                addElement.innerHTML = `<div id="my-player" class="flowplayer custom-subtitles fp-custom-playlist">
                                         <div id="flowplayer-next-video" class="col-12"></div>
                                           <div id="flowplayer-report-button">
                                             <div class="icon-report"></div>
                                          </div>
                                        </div>`;

            }else if (type == 'series') {
                addElement.innerHTML = `<div id="my-player" class="flowplayer custom-subtitles fp-custom-playlist">
                                         <div id="flowplayer-next-video" class="col-12"></div>
                                           <div id="flowplayer-report-button">
                                             <div class="icon-report"></div>
                                          </div>
                                          <div id="flowplayer-playlist" class="col-6 col-md-4 col-xl-3"></div>
                                            <div id="flowplayer-playlist-button">
                                                <div class="playlist-icon"></div>
                                            </div>
                                        </div>`;
            }

        }
    }
};
export default module;