# Nostrbots

THIS REPO IS NOT YET RELEASED! WORK IN PROGRESS.

## About

This is a Nostr microapp that allows you to publish `kind 30023` **long-form notes**, with or without an associated `kind 1111` **notification**. It also helps you to run **bots from a [Jenkins](https://www.jenkins.io/) automation server**, that publishes those notes, according to a schedule or other trigger.

![Nostrbots Use Case](https://raw.githubusercontent.com/ShadowySupercode/gitcitadel/refs/heads/master/plantUML/Nostrbots/Nostrbots%20Use%20Case.png)

You could, for instance, have a *release bot* that sends out a notification of a new software release, when that build passes. Or you could have a *test failed bot*, that warns you on Nostr, if an automated test fails.

Other possible applications are newsletters, software testing, relay/server testing, or preparing articles in advance and then sending them off at some later time.

## Setup

This repository contains an example bot (located in the */botData/anExampleBot* folder), that you can use to test it out. 

AnExampleBot:

1. publishes a kind 30023 note to the public OtherStuff relay [wss://theCitadel.nostr1.com](https://thecitadel.nostr1.com), and then 
2. publishes a kind 1111 with the `naddr` for the 30023 embedded, and then 
3. prints out hyperlinks, to view both events on [Njump](https://njump.me/).

### Command-line instructions

To run the bot in the shell, use the following commands:

This command simply accepts the argument for the folder that the bot is located in, and publishes a kind 30023, to the entire relay list

```
php bot.php anExampleBot
```

To have the bot send a kind 1111 note, change the word *false* to *true*

```
php bot.php anExampleBot note=true
```

This will post both notes to a specific relay, instead of using the relays.yml list.

```
php bot.php anExampleBot note=true wss://thecitadel.nostr1.com
```

And this will post only a long-form note, to a category within the relay list.

```
php bot.php anExampleBot note=false favorite-relays
```

### Kind 1111 notes

This repo does not publish `kind 01` notes, as I didn't want to flood the microblogging clients with notifications. I am using the new event kind 1111, which will be specially-handled by the various clients, usually as a reply to something that is not kind 01.

You can always change the kind number, in the `src/bot.php` file, if you would prefer microblogging notifications. Alter the appropriate line from `$note->setKind(kind: 1111);` to `$note->setKind(kind: 1);`. Then save the file. Nothing has to be compiled, as PHP is an interpreted language.

### Libraries

It's written in PHP 8 (with the yaml PECL extension) and [NAK](https://github.com/fiatjaf/nak), so those are two things that'll need to be installed and configured, for this to work properly.

I've set this repo up with composer, so `composer install` might be enough to get you started. Please ask for help (see [Contact info](##contact), below), if it doesn't work, so that I can fix it!

### Define your relays

Check the `src/relays.yml` file, to see if you want to add or delete any from this personal list. It's the relay list used for performing most of the functions. You can name the categories however you want, or just have no categories (then the bots use the entire list).

### Define the private key

Make sure to set the environment variable `NOSTR_BOT_KEY1` (and `NOSTR_BOT_KEY2`, etc. depending on how many bots you run) with the appropriate nsec (from whichever npub you want the bots to publish from), as that's how the info is passed to the script, for logging into private or authorized relays, or for signing events.
This can be done under Linux with `export NOSTR_BOT_KEY1=<hex private key>`

That'll work, when running the PHP scripts from the command line, but you'll have to finagle it a bit, within Jenkins, by setting the variables as _secret text_ under (for a local Jenkins) http://localhost:8081/credentials/. Like so:
![Jenkins credentials](https://i.nostr.build/4I6nT1rva3lcmaPK.png)

If you do not have a bot npub, yet, you can enter `php src/newKeys.php` on the command line and receive a full set from the PHP Helper.

### Pipelines

The bot-pipeline `jenkinsfile` needs to be manually edited, to match your bot information. Then, just go to your Jenkins instance, make sure that you have the appropriate plug-ins installed, and setup a pipeline build. You just need to tell it to use your git repo, where that repo is located, and precisely which jenkinsfile you want it to use, for that build.

![Pipeline form](https://i.nostr.build/NPzpd87V6246PSxw.png)
![Jenkinsfile form](https://i.nostr.build/diCcUHWNBtqvgDuO.png)

These will usually function like simple cron jobs, so set the `build periodically` setting, within the Jenkins GUI.

![trigger](https://i.nostr.build/lfSR00ng8qTZs2WA.png)

## Contact

If you have further questions, I can be reached at silberengel@gitcitadel.com

You can see my other repos on [GitWorkshop](https://gitworkshop.dev/p/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z) or [GitHub](https://github.com/SilberWitch).