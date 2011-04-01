
## About

This is a small library for merging, minifying and compressing static files in hope of increasing
a web pages preformance.

**Merging multiple files**  
By merging multiple files into one request the client only has to make one HTTP request, which
means we can bypass the max-2-connections-per-host-bottleneck.

**Minify/compress**  
By minifying/compressing javascript and css code one can reduce the size of the data sent to the
client by removing stuff like comments whitespaces, etc. This saves transfer time.

**Gzip**  
Using gzip output compression, the size of responses are reduced dramatically to save transfer time.

**Caching**  
By explicitly specifying how the client should cache the response, it might improve the experience
for that client and save server load.

It also caches the request's response in a file on the server to save processing time.


## How to install

- Extract archive and move the directory `static_files_compressor` to your extensions directory.
- Enable it in Symphony


## How to use
  
**Instead of referencing all your css/js files seperately like this:**

- /workspace/styles/reset.css
- /workspace/styles/library.css
- /workspace/styles/master.css
- /workspace/styles/page-frontpage.css
- /workspace/styles/page-schools.css
- /workspace/styles/page-subjects.css
- /workspace/styles/page-info.css

Everything can be combined into one request:

- `/workspace/styles/SFC.css?path=styles&compress&files=reset.css,library.css,master.css,etc..`
  
**Breaking it down:**

- `/workspace/styles/`    Necessary so that all paths in the css files are still relative.
- `SFC`                   The keyword for initializing this extension.
- `.css`                  Specifies the mode, can be either css, js or txt.
- `?path=style`           A path relative to the workspace. All files must be within this path.
- `&compress`             A compression/minify enginge will be used (only for css and js).
- `&files=f1,f2`          A comma seperated list of files within the "path"
  
**Other params:**

- `cache=normal`          Can be either normal, refresh or flush.
- `cachetimeout=10`       If some files are remote files, cache cannot be more than 10 sec old.
- `outputcompress=0`      Disable output compression, gzip.
- `debug`                 Adding this param shows debug mode with FirePHP.


**XSLT utility**  
This package also includes a small XSLT utility in `utilities/`. It is moved to `workspace/utilities`
when the extension is installed. Please see the file's documentation on how it works.


**Debug**  
Debug info is generated if `?debug` param is added. The info is sent via FirePHP so you need to
install the [FirePHP extension for Firebug][1]. You must also make the SFC request directly to view
the debug info.


## License

3rd party code each has their own license.
 

Copyright (c) 2011 Newzoo Ltd.

Permission is hereby granted, free of charge, to any person obtaining a copy of this
software and associated documentation files (the "Software"), to deal in the Software
without restriction, including without limitation the rights to use, copy, modify, merge,
publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons
to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or
substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.


  [1]: http://www.firephp.org/