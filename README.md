phorum2phpbb
============

phpBB plugin (convertor) to migrate from Phorum5 to phpBB 3.0.x

### Caution

This code is alpha quality! Use at your own risk and DO BACKUP everything, this
code may affect / at least the target database. Some tables are truncated before
import!

Source database is accessed read only, but DO BACKUP it as well. Better safe
than sorry.

### History

This is quite old code, I used back in 2009 to migrate one quite huge Phorum5 board to phpBB3.

Since then, there are still people finding it useful.

You can find the discussion about it <a href="https://www.phpbb.com/community/viewtopic.php?f=65&t=1690005">in phpbb.com community board</a>.

Feel free to leave feedback and/or contribute.

### Changelog

2018/11/12 - updated description, clarified the attachments handling
2014/12/19 - imported users was not members of any group, now they are
             automatically put in groups "new users" and "registered users"
           - internal type of imported users fixed
2009/07/12 First alpha release. Functions renamed to phorum5_ prefix.
2009/07/10 Start of development


### Usage

- download this code as ZIP (link is the right)
- extract it in the phpbb root directory before you install phpbb
- during installation of phpbb, there will be available conversion from Phorum

### After conversion

Attachment files are not handeled! If you have the files stored in files on disk, copy the attachments folder from phorum into files folder of phpbb.
In case, your phorum installation was set to store attachments into database, use 'store files on disk' module to convert the phorum attachments (in database) to attachments on disk (thanks to <a href="https://github.com/classofoutcasts">classofoutcasts</a>)

Don't forget to do some final steps after conversion:
- Resyncronize statistics
- Build your new search indices

### Final notes

- this convertor implements only the minimal set of features. PMs, avatars, user permissions
and a lot of other settings is silently ignored.
- you need to setup and customize your new phpbb installation by yourself.
- try it out, improve it, share it :-)
- it would be nice to know if this code helped ;-)
