# FILES

The file documents the files and locations used in Mediawiki Extension Parsifal

The intended installation point of Parsifal is extensions/Parsifal.


## Directory Structure

| Directory         |  Contents |
| -----------       | ----------- |
| /codemirror       |  Our own adaptations for codemirror |
| /css              |  css files, may be part of resource bundles      |
| /doc              |  documentation of the extension in md format       |
| /endpoints        |  files serving as additional API endpoints  |
| /formats_latex    |  contains the latex precompiled versions of the templates  |
| /formats_pdflatex   | pdflatex precompiled versions of the templates  |
| /js               |  js  files, may be part of resource bundles | 
| /local            |  local tex files for inclusion by the template files|
| /log              |  log files of the extension |
| /php              |  php source files |
| /template         |  templates for external processors (such as LaTeX, HTML error parser etc ) |
| /vendor           |  where we keep entire vendor files |
| config.php        |  configuration of the extension |
| extension.json    |  standard Mediawiki extension json file |
| install           |  shell script for fixing the extension |
| Parsifal.php      |  the main entry point class for extension.json hooks|
| README.md         |  a general readme |
| .gitignore        |  gitignore file |
 
  
## File extensions

### Goals

1. Ease of switching between different tool chains and production processes while maintaining... 
1. Support for debug, reproducibility and audit of the typesetting process.
1. Possibility of a flexible combination of available tools to custom tool chains.

### Implementation

1. All functions work file-based. Direct handover of data without file intermediaries, for example via pipes, is avoided.
1. All functions allow a specification of inFinal and outFinal string fragments, which are located between hash and extension.
2. All functions check the existence of the required input files.
3. Thus, by adapting the inFinal and outFinal fragments in the tool chain, arbitrary production chains can be built.


| Pattern              |  Usage |
| -----------          | ----------- |
| 0815.t             |  The subsystem translating PDF annotations and links |
| 0815_pc_latex.*      |  The subsystem translating PDF annotations and links |
| 0815_pc_latex.*      | Files used in the latex precompilation process |
| 0815_pc_pdflatex.*   | Files used in the pdflatex precompilation process |
| 0815_node.*          | Files generated in the  |
| 0815_mt1                    | Files generated in the mutool process  |
| MediaWiki:ParsifalTemplate  | Directory of all templates which are used  |
| MediaWiki:ParsifalTemplate/`tag`  | Template in use for markup tag `tag`  |

# Tools

* RawTeX to TeX
* TeX to DVI
* TeX to PDF
* DVI to PNG 
* PDF to PNG 
* PDF to aHTML 

 

# Processes

## Capture Raw TeX source

* The raw TeX source is captured by PHP as the text between the lines containing the starting and ending hook tags. 
* The newline characters of these lines is not part of the raw TeX source. This is essential, since a hash of the raw source will be used for identification.
* For all further processing, the md5 hash of the raw TeX source in hexadecimal coding is used for identification of all derived files.

## Cook Raw TeX source

* Now the raw TeX source is embedded into an environment in which it will be TeX-compiled. We call this the cooked TeX source.
* This environment provides standard settings, macros, support packages and more.
* Error messages, log files, intermediary files (such as .aux) and more will depend on the exact form of the cooked TeX source.
 Thus, all file variants are kept seperate.

There are several possibilities for cooking:
1. Compile this raw TeX source after embedding it into one of several templates `$template`
2. Compile this raw TeX source after embedding it into a structure which uses a precompiled form of template `$template` with a precompilation
 under latex.
3. Compile this raw TeX source after embedding it into a structure which uses a precompiled form of template `$template` with a precompilation
  under pdflatex. 

One raw TeX source may be 

$hash$tag.tex 
$hash$tag_pc_latex.tex
$hash$tag_pc_pdflatex.tex


























