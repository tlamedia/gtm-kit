var ncp = require('ncp').ncp;

ncp.limit = 16;

ncp('src/images', 'assets/images', function (err) {
	if (err) {
		return console.error(err);
	}
	console.log('Images copied!');
});
