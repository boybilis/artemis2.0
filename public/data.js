export const topics = [
  {
    id: 1,
    title: "CSS Introduction",
    lessons: [
      {
        title: "CSS Home & Introduction",
        videoUrl: "https://www.youtube.com/embed/1Rs2ND1ryYc",
        notes: "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat."
      },
      {
        title: "CSS Syntax & Selectors",
        videoUrl: "https://www.youtube.com/embed/l1mER1ZzY1Y",
        notes: "Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum."
      }
    ],
    quiz: [
      {
        question: "What does CSS stand for?",
        options: ["Cascading Style Sheets", "Creative Style System", "Computer Style Sheets", "Colorful Style Sheets"],
        answer: 0
      },
      {
        question: "Which HTML tag is used to define an internal style sheet?",
        options: ["<script>", "<css>", "<style>", "<design>"],
        answer: 2
      }
    ]
  },
  {
    id: 2,
    title: "CSS Syntax Deep Dive",
    lessons: [
      {
        title: "Comments & Selectors",
        videoUrl: "https://www.youtube.com/embed/yfoY53QXEnI",
        notes: "Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo."
      },
      {
        title: "Combinators & Pseudo-elements",
        videoUrl: "https://www.youtube.com/embed/mHAt-vYvFfM",
        notes: "Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet."
      }
    ],
    quiz: [
      {
        question: "How do you insert a comment in a CSS file?",
        options: ["// this is a comment", "/* this is a comment */", "' this is a comment", "// this is a comment //"],
        answer: 1
      },
      {
        question: "Which selector is used to style an element with a specific ID?",
        options: [".id", "#id", "*id", "id="],
        answer: 1
      }
    ]
  },
  {
    id: 3,
    title: "CSS Colors",
    lessons: [
      {
        title: "Colors, RGB, HEX, HSL",
        videoUrl: "https://www.youtube.com/embed/fD2Zp4baS24",
        notes: "Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae."
      }
    ],
    quiz: [
      {
        question: "Which property is used to change the background color?",
        options: ["color", "bgcolor", "background-color", "surface-color"],
        answer: 2
      },
      {
        question: "How do you write 'Hello World' in an HSL color format?",
        options: ["hsl(0, 100%, 50%)", "rgb(255, 0, 0)", "#FF0000", "red"],
        answer: 0
      }
    ]
  },
  {
    id: 4,
    title: "CSS Backgrounds",
    lessons: [
      {
        title: "Background Color & Images",
        videoUrl: "https://www.youtube.com/embed/yVIsP-O0n1M",
        notes: "At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident."
      }
    ],
    quiz: [
      {
        question: "Which property is used to set the background image of an element?",
        options: ["background-image", "image-background", "bg-image", "content-image"],
        answer: 0
      }
    ]
  },
  {
    id: 5,
    title: "CSS Borders",
    lessons: [
      {
        title: "Borders & Rounded Corners",
        videoUrl: "https://www.youtube.com/embed/n4p_nC-pTTo",
        notes: "Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est."
      }
    ],
    quiz: [
      {
        question: "Which property is used to change the border width?",
        options: ["border-width", "width-border", "thickness", "border-style"],
        answer: 0
      }
    ]
  },
  {
    id: 6,
    title: "CSS Margins & Box Model",
    lessons: [
      {
        title: "Margins & Box Model",
        videoUrl: "https://www.youtube.com/embed/nSst4-WbEzU",
        notes: "Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus."
      }
    ],
    quiz: [
      {
        question: "In the CSS box model, which one is the outermost layer?",
        options: ["Padding", "Border", "Margin", "Content"],
        answer: 2
      }
    ]
  },
  {
    id: 7,
    title: "CSS Padding & Outline",
    lessons: [
      {
        title: "Padding & Outlines",
        videoUrl: "https://www.youtube.com/embed/1Rs2ND1ryYc",
        notes: "Accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur."
      }
    ],
    quiz: [
      {
        question: "Which property is used to change the left padding of an element?",
        options: ["padding-left", "left-padding", "padding: left", "spacing-left"],
        answer: 0
      }
    ]
  },
  {
    id: 8,
    title: "CSS Text",
    lessons: [
      {
        title: "Text Formatting & Alignment",
        videoUrl: "https://www.youtube.com/embed/K8I8lSAsa6I",
        notes: "Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur? Lorem ipsum dolor sit amet, consectetur adipiscing."
      }
    ],
    quiz: [
      {
        question: "Which property is used to change the color of text?",
        options: ["text-color", "fgcolor", "color", "font-color"],
        answer: 2
      }
    ]
  },
  {
    id: 9,
    title: "CSS Fonts",
    lessons: [
      {
        title: "Font Families & Styles",
        videoUrl: "https://www.youtube.com/embed/hOshmK6CscA",
        notes: "Laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident."
      }
    ],
    quiz: [
      {
        question: "Which CSS property controls the text size?",
        options: ["font-style", "text-size", "font-size", "text-style"],
        answer: 2
      }
    ]
  },
  {
    id: 10,
    title: "CSS Links, Lists & Tables",
    lessons: [
      {
        title: "Links, Lists & Tables",
        videoUrl: "https://www.youtube.com/embed/cy9Hh6VvXN4",
        notes: "Similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque."
      }
    ],
    quiz: [
      {
        question: "How do you remove the underline from all hyperlinks?",
        options: ["a {text-decoration:none;}", "a {underline:none;}", "a {decoration:no-underline;}", "a {text-style:none;}"],
        answer: 0
      }
    ]
  }
];

export const finalExam = [
  {
    question: "What is the correct CSS syntax?",
    options: ["body {color: black;}", "{body;color:black;}", "body:color=black;", "{body:color=black;}"],
    answer: 0
  },
  {
    question: "How do you select an element with id 'demo'?",
    options: [".demo", "#demo", "*demo", "demo"],
    answer: 1
  },
  {
    question: "How do you select elements with class name 'test'?",
    options: ["*test", "#test", ".test", "test"],
    answer: 2
  },
  {
    question: "How do you display hyperlinks without an underline?",
    options: ["a {decoration:no-underline;}", "a {text-decoration:none;}", "a {text-decoration:no-underline;}", "a {underline:none;}"],
    answer: 1
  },
  {
    question: "Which property is used to change the left margin of an element?",
    options: ["margin-left", "padding-left", "indent", "left-margin"],
    answer: 0
  }
];
