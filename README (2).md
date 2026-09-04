# MCEN2003 Machine Dynamics — Tutorial 1 workbook

Everything for the interactive Tutorial 1 (Kinematics) workbook.
Curtin University, School of Civil and Mechanical Engineering.

## What's in here

```
index.html          the workbook, complete in one file  <- deploy this
robots.txt          asks search engines not to index the page
source/
  index.html        the same page, split up for reading and editing
  workbook.css      all styling (light and dark themes)
  workbook.js       all the code
```

`index.html` in the top folder and the three files in `source/` are the same
workbook. The top one has everything inlined so it can be uploaded on its own;
the `source/` version is split so it can be read and edited.

## Deploying it

Upload `index.html` to Canvas, GitHub Pages, or any web host, and give students
that link.

**It must be served from a web address.** If a student downloads the file and opens
it from their iPad's Files app, iOS will not run the JavaScript: the page will look
completely normal and nothing will respond. This is an iOS restriction on local
files and cannot be worked around in the page. On a laptop, opening the downloaded
file works fine.

## What it does

- Five tutorial questions with the original figures from the question sheet
- The worked solution stays locked until the student has attempted every part.
  Right or wrong makes no difference; an attempt is what unlocks it
- An idea button giving the approach, formulas and a step-by-step plan, with no numbers
- Wrong answers are matched against common misconceptions and explained by name,
  plus sign and order-of-magnitude checks for slips with no named cause
- Both the original solution sheet and a step-by-step version, with the reasoning
  behind every step available on request
- Interactive acceleration / velocity / displacement graphs
- A multi-page working-out area: handwriting with pressure, and a typed pad that
  calculates each line as you write it
- Export of the student's own work to PDF or Word

Nothing is sent anywhere. All work stays in the student's own browser on their own
device, which is why any number of students can use it at once, and why it keeps
working if the wifi drops.

## Editing it

Everything you would normally change sits at the top of `source/workbook.js`:

| block | holds |
| --- | --- |
| `QUESTIONS` | statements, figures, given data, hints, answers, solutions |
| `TRAPS` | wrong answers matched to the misconception behind each |
| `CONCEPT`, `WHY` | what each question tests, and why each step works |
| `GRAPHS` | the functions drawn in the Motion graphs tab |

To add Tutorial 2, copy the folder, replace those blocks, and crop the figures out
of that tutorial's question sheet.

## Dependencies

None. Plain HTML, CSS and JavaScript. No frameworks, no build step, no external
scripts. The only network request is the IBM Plex font from Google Fonts, and the
page falls back to system fonts without it. Figures are embedded in the file.
