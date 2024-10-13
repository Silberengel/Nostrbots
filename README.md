# Nostrbots

## About

This is a Nostr microapp that allows you to run bots (written in PHP) from a Jenkins server. It contains three botscripts, that you can use to test, or you can come up with your own bots. :smiley:

It's written in PHP 8 with the yaml extension, and some of the underlying functionality is from [PHP Helper](https://github.com/nostrver-se/nostr-php), so those are two things that'll need to be installed and configured, for this to work properly. Feel free to do this with a different Nostr library, as the `jenkinsfile` doesn't care, it just lists commands to call the bots and handles the timing of the builds.

## Setup

I've set this repo up with composer, so `composer install` might be enough to get you started. Please ask for help, if it doesn't work, so that I can fix it! 😊

### Define your relays

Check the `relays.yml` file, to see if you want to add or delete any from this personal list. It's the relay list used for performing most of the functions. You can name the categories however you want, or just have no categories (then the bots always just use the entire list).

### Define the private key

Make sure to set the environment variables `NOSTR_BOT_KEY1` (and `NOSTR_BOT_KEY2`, etc. depending on how many bots you run) with the appropriate nsec (from whichever npub you want the bots to publish from), as that's how the info is passed to the script, for logging into private or authorized relays, or for signing events.
This can be done under Linux with `export NOSTR_BOT_KEY1=<hex private key>`

If you do not have a bot npub, yet, you can enter `php src/newKeys.php` on the command line and receive a full set from the PHP Helper.

### Jenkins

In your Jenkins server, just setup a pipeline script to run `@hourly` (or however often you need, to hit all of the triggers), and it'll start each bot, according to the `when` conditions you have set in the script.

Make sure, if you have a `when` condition for a weekday, that you also include an hour condition, so that it doesn't get triggered every hour of that day.

## Contact

If you have further questions, I can be reached nostr:npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z

You can see my other repos on [GitWorkshop](https://gitworkshop.dev/p/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z) or [GitHub](https://github.com/SilberWitch).