<template>

    <div>

        <div id="player" v-html="video">

        </div>

    </div>

</template>

<script>
    export default {
        name: 'movie-player-embed',
        data() {
            return {
                movie_title: '',
                active: '',
                video: '',
            }
        },
        mounted() {

            axios.get('/api/v1/get/watch/movie/' + this.$route.params.id).then(response => {
                if (response.status === 200) {
                    this.video = '<iframe width="100%" height="100%" src="'+ response.data.data.video[0].video +'" frameborder="0" allowfullscreen></iframe>';
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
                });
            });
            },

    }
</script>

<style scoped>

    #player {
        position: fixed;
        width: 100%;
        left: 0;
    }

</style>