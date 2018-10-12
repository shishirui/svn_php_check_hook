# svn_php_check_hook

Automatically checking for php syntax errors before commit to svn.

### features

1. Check php syntax errors.
1. Check commit message can not be empth and the length must be greater than 10.
1. Check file encoding must be `UTF-8`.

### Installation 

1. Clone the whole repository to  you svn server.
1. Copy `pre-commit` file into `hooks` folders of repository.
1. Modify the checker path in `pre-commit`.

That's all.
