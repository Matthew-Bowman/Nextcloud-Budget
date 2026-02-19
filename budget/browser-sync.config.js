module.exports = {
    proxy: "https://nextcloud.home.lab",
    files: [
        "css/**/*.css",
        "js/**/*.js",
        "templates/**/*.php"
    ],
    port: 3001,
    open: false,
    reloadDebounce: 200,
    ghostMode: false,
    https: true,
}