### Installation

```
composer global require happy/happy
```
To make use of **happy** command globaly make sure to place that
your composers vendor bin directory is in your **$PATH**.

Once installed **happy** command should be available globaly.

### Database Dumper

Database dumper is a tool to dump a database from a remote server to your local database.
To use it just run
```
happy db:dump
```

Running it for the first time will create a **.happy** file in your project and add it to .gitignore so you dont commit it by accident.

Inside the .happy file you should find two variables that you need to fill in before dumping databases.

```
REMOTE_SERVER_HOST= #forge@your-server.com
REMOTE_PROJECT_PATH= #/home/forge/your-project
```

Once you define your server host and project path you can run the dump again and it should copy over the remote database to your local environment.

> IMPORTANT - db:dump works ONLY with MYSQL right now. 