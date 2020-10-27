var words = {
	"nouns":[ // [noun,plural] USE '' TO AUTOGENERATE
		["abyss", "abysses"], ["alumnus", "alumni"], ["analysis", "analyses"], ["aquarium", "aquaria"],
		["arch", "arches"], ["atlas", "atlases"], ["axe", "axes"], ["baby", "babies"], ["bacterium", "bacteria"],
		["batch", "batches"], ["beach", "beaches"], ["brush", "brushes"], ["bus", "buses"], ["calf", "calves"],
		["chateau", "chateaux"], ["cherry", "cherries"], ["child", "children"], ["church", "churches"],
		["circus", "circuses"], ["city", "cities"], ["cod", "cod"], ["copy", "copies"], ["crisis", "crises"],
		["curriculum", "curricula"], ["deer", "deer"], ["dictionary", "dictionaries"], ["domino", "dominoes"],
		["dwarf", "dwarves"], ["echo", "echoes"], ["elf", "elves"], ["emphasis", "emphases"], ["family", "families"],
		["fax", "faxes"], ["fish", "fish"], ["flush", "flushes"],["fly", "flies"], ["foot", "feet"],
		["fungus", "fungi"], ["half", "halves"], ["hero", "heroes"], ["hippopotamus", "hippopotami"],
		["hoax", "hoaxes"], ["hoof", "hooves"], ["index", "indexes"], ["iris", "irises"], ["kiss", "kisses"],
		["knife", "knives"], ["lady", "ladies"], ["leaf", "leaves"], ["life", "lives"], ["loaf", "loaves"],
		["man", "men"], ["mango", "mangoes"], ["memorandum", "memoranda"], ["mess", "messes"], ["moose", "moose"],
		["motto", "mottoes"], ["mouse", "mice"], ["nanny", "nannies"], ["neurosis", "neuroses"], ["nucleus", "nuclei"],
		["oasis", "oases"], ["octopus", "octopi"], ["party", "parties"], ["pass", "passes"], ["penny", "pennies"],
		["person", "people"], ["plateau", "plateaux"], ["poppy", "poppies"], ["potato", "potatoes"], ["quiz", "quizzes"],
		["reflex", "reflexes"], ["scarf", "scarves"], ["scratch", "scratches"], ["series", "series"],
		["sheaf", "sheaves"], ["sheep", "sheep"], ["shelf", "shelves"], ["species", "species"], ["splash", "splashes"],
		["spy", "spies"], ["stitch", "stitches"], ["story", "stories"], ["syllabus", "syllabi"], ["tax", "taxes"],
		["thesis", "theses"], ["thief", "thieves"], ["tomato", "tomatoes"], ["tooth", "teeth"],
		["tornado", "tornadoes"], ["try", "tries"], ["volcano", "volcanoes"], ["waltz", "waltzes"],
		["wash", "washes"], ["watch", "watches"], ["wharf", "wharves"], ["wife", "wives"], ["boat", "boats"],
		["house", "houses"], ["cat", "cats"], ["river", "rivers"], ["bus", "buses"], ["wish", "wishes"],
		["pitch", "pitches"], ["box", "boxes"], ["penny", "pennies"], ["spy", "spies"], ["baby", "babies"],
		["city", "cities"], ["daisy", "daisies"], ["woman", "women"], ["man", "men"], ["child", "children"],
		["tooth", "teeth"], ["foot", "feet"], ["person", "people"], ["leaf", "leaves"], ["mouse", "mice"],
		["goose", "geese"], ["half", "halves"], ["knife", "knives"], ["wife", "wives"], ["life", "lives"],
		["elf", "elves"], ["loaf", "loaves"], ["potato", "potatoes"], ["tomato", "tomatoes"], ["cactus", "cacti"],
		["focus", "foci"], ["fungus", "fungi"], ["nucleus", "nuclei"], ["syllabus", "syllabi"],
		["analysis", "analyses"], ["diagnosis", "diagnoses"], ["oasis", "oases"], ["thesis", "theses"],
		["crisis", "crises"], ["phenomenon", "phenomena"], ["criterion", "criteria"], ["datum", "data"],
		["human", ""], ["apple", ""], ["computer", ""], ["helicopter", ""], ["dancer", ""],
		["strawberry", "strawberries"], ["fan", ""], ["pineapple", ""], ["thing", ""]
	],
	"verbs":[
		// [verb, pastTense, 3rdPersonPresent, pastParticiple, presentParticiple, hasObject] USE '' TO AUTOGENERATE; FOR pastParticiple TO COPY FROM pastTense
		['are','was','is','been','being',1,"were"],
		['have','had','has','','',1],
		['like','','like','','',1],
		['love','','','','',1],
		['live','','','',''],
		['die','','','','dying'],
		['kill','','','','',1],
		['run','ran','','','running'],
		['hate','','','','',1],
		['murder','','','','',1],
		['confuse','','','','',1],
		['overcomplicate','','','','',1],
		['underestimate','','','','',1],
		['whack','','','','',1],
		['scare','','','','',1],
		['ship','','','','shipping',1],
		['experience','','','','',1],
		['invade','','','','',1],
		['dance','','','',''],
		['code','','','',''],
		['program','programmed','','','programming'],
		['doodle','','','',''],
		['type','','','',''],
		['cry','cried','cries','',''],
		['lie','','','','lying'],
		['talk','','','',''],
		['listen','','','',''],
		['exercise','','','',''],
		['contribute','','','',''],
		['ski','','','',''],
		['exist','','','',''],
		['hang','hung','','','',1],
	],
	"adjectives":[
		"sheepish", "overweight", "magical", "fake", "nonexistent", "radioactive",
		"aback", "abaft", "abandoned", "abashed", "aberrant", "abhorrent", "abiding",
		"abject", "ablaze", "able", "abnormal", "aboard", "aboriginal", "abortive",
		"abounding", "abrasive", "abrupt", "absent", "absolute", "absorbed",
		"absorbing", "abstracted", "absurd", "abundant", "abusive", "academic",
		"acceptable", "accessible", "accidental", "acclaimed", "accomplished",
		"accurate", "aching", "acid", "acidic", "acoustic", "acrid", "acrobatic",
		"active", "actual", "actually", "ad hoc", "adamant", "adaptable", "addicted",
		"additional", "adept", "adhesive", "adjoining", "administrative", "admirable",
		"admired", "adolescent", "adorable", "adored", "advanced", "adventurous",
		"affectionate", "afraid", "aged", "aggravating", "aggressive", "agile",
		"agitated", "agonizing", "agreeable", "ahead", "ajar", "alarmed", "alarming",
		"alcoholic", "alert", "alienated", "alike", "alive", "all", "alleged",
		"alluring", "aloof", "altruistic", "amazing", "ambiguous", "ambitious",
		"amiable", "ample", "amuck", "amused", "amusing", "anchored", "ancient",
		"angelic", "angry", "anguished", "animated", "annoyed", "annoying", "annual",
		"another", "antique", "antsy", "anxious", "any", "apathetic", "appetizing",
		"apprehensive", "appropriate", "apt", "aquatic", "arctic", "arid", "aromatic",
		"arrogant", "artistic", "ashamed", "asleep", "aspiring", "assorted", "assured",
		"astonishing", "athletic", "attached", "attentive", "attractive", "auspicious",
		"austere", "authentic", "authorized", "automatic", "available", "avaricious",
		"average", "awake", "aware", "awesome", "awful", "awkward", "axiomatic",
		"babyish", "back", "bad", "baggy", "barbarous", "bare", "barren", "bashful",
		"basic", "batty", "bawdy", "beautiful", "beefy", "befitting", "belated",
		"belligerent", "beloved", "beneficial", "bent", "berserk", "best", "better",
		"bewildered", "bewitched", "big", "big-hearted", "billowy", "biodegradable",
		"bite-sized", "biting", "bitter", "bizarre", "black", "black-and-white",
		"bland", "blank", "blaring", "bleak", "blind", "blissful", "blond", "bloody",
		"blue", "blue-eyed", "blushing", "bogus", "boiling", "bold", "bony", "boorish",
		"bored", "boring", "bossy", "both", "bouncy", "boundless", "bountiful", "bowed",
		"brainy", "brash", "brave", "brawny", "breakable", "breezy", "brief", "bright",
		"brilliant", "brisk", "broad", "broken", "bronze", "brown", "bruised", "bubbly",
		"bulky", "bumpy", "buoyant", "burdensome", "burly", "bustling", "busy",
		"buttery", "buzzing", "cagey", "calculating", "callous", "calm", "candid",
		"canine", "capable", "capital", "capricious", "carefree", "careful", "careless",
		"caring", "cautious", "cavernous", "ceaseless", "celebrated", "certain",
		"changeable", "charming", "cheap", "cheeky", "cheerful", "cheery", "chemical",
		"chief", "childlike", "chilly", "chivalrous", "chubby", "chunky", "circular",
		"civil", "clammy", "classic", "classy", "clean", "clear", "clear-cut", "clever",
		"cloistered", "close", "closed", "cloudy", "clueless", "clumsy", "cluttered",
		"coarse", "coherent", "cold", "colorful", "colorless", "colossal", "combative",
		"comfortable", "common", "compassionate", "competent", "competitive",
		"complete", "complex", "complicated", "composed", "comprehensive", "concerned",
		"concrete", "condemned", "condescending", "confident", "confused", "conscious",
		"considerate", "consistent", "constant", "contemplative", "content",
		"conventional", "convincing", "convoluted", "cooing", "cooked", "cool",
		"cooperative", "coordinated", "corny", "corrupt", "costly", "courageous",
		"courteous", "cowardly", "crabby", "crafty", "craven", "crazy", "creamy",
		"creative", "creepy", "criminal", "crisp", "critical", "crooked", "crowded",
		"cruel", "crushing", "cuddly", "cultivated", "cultural", "cultured",
		"cumbersome", "curious", "curly", "curved", "curvy", "cut", "cute",
		"cylindrical", "cynical", "daffy", "daily", "damaged", "damaging", "damp",
		"dangerous", "dapper", "daring", "dark", "darling", "dashing", "dazzling",
		"dead", "deadly", "deadpan", "deafening", "dear", "dearest", "debonair",
		"decayed", "deceitful", "decent", "decimal", "decisive", "decorous", "deep",
		"deeply", "defeated", "defective", "defenseless", "defensive", "defiant",
		"deficient", "definite", "definitive", "delayed", "delectable", "delicate",
		"delicious", "delightful", "delirious", "demanding", "demonic", "dense",
		"dental", "dependable", "dependent", "depraved", "depressed", "deranged",
		"descriptive", "deserted", "desperate", "despicable", "detailed", "determined",
		"devilish", "devoted", "didactic", "different", "difficult", "digital",
		"dilapidated", "diligent", "dim", "diminutive", "dimpled", "dimwitted",
		"direct", "direful", "dirty", "disagreeable", "disastrous", "discreet",
		"discrete", "disfigured", "disguised", "disgusted", "disgusting", "dishonest",
		"disillusioned", "disloyal", "dismal", "dispensable", "distant", "distinct",
		"distorted", "distraught", "distressed", "disturbed", "divergent", "dizzy",
		"domineering", "dopey", "doting", "double", "doubtful", "downright", "drab",
		"draconian", "drafty", "drained", "dramatic", "dreary", "droopy", "drunk",
		"dry", "dual", "dull", "dusty", "dutiful", "dynamic", "dysfunctional", "each",
		"eager", "early", "earnest", "earsplitting", "earthy", "eastern", "easy",
		"easy-going", "eatable", "economic", "ecstatic", "edible", "educated",
		"educational", "efficacious", "efficient", "eight", "elaborate", "elastic",
		"elated", "elderly", "electric", "electrical", "electronic", "elegant",
		"elementary", "elfin", "elite", "elliptical", "emaciated", "embarrassed",
		"embellished", "eminent", "emotional", "empty", "enchanted", "enchanting",
		"encouraging", "endurable", "energetic", "enlightened", "enormous", "enraged",
		"entertaining", "enthusiastic", "entire", "envious", "environmental", "equable",
		"equal", "equatorial", "erect", "erratic", "essential", "esteemed", "ethereal",
		"ethical", "euphoric", "evanescent", "evasive", "even", "evergreen",
		"everlasting", "every", "evil", "exalted", "exasperated", "excellent",
		"excitable", "excited", "exciting", "exclusive", "exemplary", "exhausted",
		"exhilarated", "existing", "exotic", "expensive", "experienced", "expert",
		"extensive", "extra-large", "extra-small", "extraneous", "extroverted",
		"exuberant", "exultant", "fabulous", "faded", "failing", "faint", "fair",
		"faithful", "fake", "fallacious", "false", "familiar", "famous", "fanatical",
		"fancy", "fantastic", "far", "far-flung", "far-off", "faraway", "fascinated",
		"fast", "fat", "fatal", "fatherly", "faulty", "favorable", "favorite",
		"fearful", "fearless", "federal", "feeble", "feigned", "feisty", "feline",
		"female", "feminine", "fertile", "festive", "few", "fickle", "fierce", "filthy",
		"financial", "fine", "finicky", "finished", "firm", "first", "firsthand",
		"fitting", "five", "fixed", "flagrant", "flaky", "flamboyant", "flashy", "flat",
		"flawed", "flawless", "flickering", "flimsy", "flippant", "floppy", "flowery",
		"fluffy", "flufy", "fluid", "flustered", "fluttering", "foamy", "focused",
		"fond", "foolhardy", "foolish", "forceful", "foregoing", "foreign", "forgetful",
		"forked", "formal", "former", "forsaken", "forthright", "fortunate", "four",
		"fragile", "fragrant", "frail", "frank", "frantic", "frayed", "free",
		"freezing", "French", "frequent", "fresh", "fretful", "friendly", "frightened",
		"frightening", "frigid", "frilly", "frivolous", "frizzy", "front", "frosty",
		"frothy", "frozen", "frugal", "fruitful", "frustrating", "full", "fumbling",
		"functional", "funny", "furry", "furtive", "fussy", "future", "futuristic",
		"fuzzy", "gabby", "gainful", "gamy", "gaping", "gargantuan", "garrulous",
		"gaseous", "gaudy", "general", "generous", "gentle", "genuine", "ghastly",
		"giant", "giddy", "gifted", "gigantic", "giving", "glamorous", "glaring",
		"glass", "gleaming", "gleeful", "glib", "glistening", "glittering", "global",
		"gloomy", "glorious", "glossy", "glum", "godly", "golden", "good",
		"good-natured", "goofy", "gorgeous", "graceful", "gracious", "grand",
		"grandiose", "granular", "grateful", "gratis", "grave", "gray", "greasy",
		"great", "greedy", "green", "gregarious", "grey", "grieving", "grim", "grimy",
		"gripping", "grizzled", "groovy", "gross", "grotesque", "grouchy", "grounded",
		"growing", "growling", "grown", "grubby", "gruesome", "grumpy", "guarded",
		"guiltless", "guilty", "gullible", "gummy", "gusty", "guttural", "habitual",
		"hairy", "half", "hallowed", "halting", "handmade", "handsome", "handy",
		"hanging", "hapless", "happy", "happy-go-lucky", "hard", "hard-to-find",
		"harebrained", "harmful", "harmless", "harmonious", "harsh", "hasty", "hateful",
		"haunting", "heady", "healthy", "heartbreaking", "heartfelt", "hearty",
		"heavenly", "heavy", "hefty", "hellish", "helpful", "helpless", "hesitant",
		"hidden", "hideous", "high", "high-level", "high-pitched", "highfalutin",
		"hilarious", "hissing", "historical", "hoarse", "holistic", "hollow",
		"homeless", "homely", "honest", "honorable", "honored", "hopeful", "horrible",
		"horrific", "hospitable", "hot", "huge", "hulking", "humble", "humdrum",
		"humiliating", "humming", "humongous", "humorous", "hungry", "hurried", "hurt",
		"hurtful", "hushed", "husky", "hypnotic", "hysterical", "icky", "icy", "ideal",
		"idealistic", "identical", "idiotic", "idle", "idolized", "ignorant", "ill",
		"ill-fated", "ill-informed", "illegal", "illiterate", "illustrious", "imaginary",
		"imaginative", "immaculate", "immaterial", "immediate", "immense", "imminent",
		"impartial", "impassioned", "impeccable", "imperfect", "imperturbable", "impish",
		"impolite", "important", "imported", "impossible", "impractical",
		"impressionable", "impressive", "improbable", "impure", "inborn",
		"incandescent", "incomparable", "incompatible", "incompetent", "incomplete",
		"inconclusive", "inconsequential", "incredible", "indelible", "indolent",
		"industrious", "inexpensive", "inexperienced", "infamous", "infantile",
		"infatuated", "inferior", "infinite", "informal", "innate", "inner", "innocent",
		"inquisitive", "insecure", "insidious", "insignificant", "insistent",
		"instinctive", "instructive", "insubstantial", "intelligent", "intent",
		"intentional", "interesting", "internal", "international", "intrepid",
		"intrigued", "invincible", "irate", "ironclad", "irresponsible", "irritable",
		"irritating", "itchy", "jaded", "jagged", "jam-packed", "jaunty", "jazzy",
		"jealous", "jittery", "jobless", "joint", "jolly", "jovial", "joyful", "joyous",
		"jubilant", "judicious", "juicy", "jumbled", "jumbo", "jumpy", "junior",
		"juvenile", "kaleidoscopic", "kaput", "keen", "key", "kind", "kindhearted",
		"kindly", "klutzy", "knobby", "knotty", "knowing", "knowledgeable", "known",
		"kooky", "kosher", "labored", "lackadaisical", "lacking", "lame", "lamentable",
		"languid", "lanky", "large", "last", "lasting", "late", "latter", "laughable",
		"lavish", "lawful", "lazy", "leading", "leafy", "lean", "learned", "left",
		"legal", "legitimate", "lethal", "level", "lewd", "light", "lighthearted",
		"likable", "like", "likeable", "likely", "limited", "limp", "limping", "linear",
		"lined", "liquid", "literate", "little", "live", "lively", "livid", "living",
		"loathsome", "logical", "lone", "lonely", "long", "long-term", "longing",
		"loose", "lopsided", "lost", "loud", "loutish", "lovable", "lovely", "loving",
		"low", "lowly", "loyal", "lucky", "ludicrous", "lumbering", "luminous", "lumpy",
		"lush", "lustrous", "luxuriant", "luxurious", "lying", "lyrical", "macabre",
		"macho", "mad", "maddening", "made-up", "madly", "magenta", "magical",
		"magnificent", "majestic", "major", "makeshift", "male", "malicious", "mammoth",
		"maniacal", "many", "marked", "married", "marvelous", "masculine", "massive",
		"material", "materialistic", "mature", "meager", "mealy", "mean", "measly",
		"meaty", "medical", "mediocre", "medium", "meek", "melancholy", "mellow",
		"melodic", "melted", "memorable", "menacing", "mental", "merciful", "mere",
		"merry", "messy", "metallic", "mighty", "mild", "military", "milky", "mindless",
		"miniature", "minor", "minty", "minute", "miscreant", "miserable", "miserly",
		"misguided", "mistaken", "misty", "mixed", "moaning", "modern", "modest",
		"moist", "moldy", "momentous", "monstrous", "monthly", "monumental", "moody",
		"moral", "mortified", "motherly", "motionless", "mountainous", "muddled",
		"muddy", "muffled", "multicolored", "mundane", "murky", "mushy", "musty",
		"mute", "muted", "mysterious", "naive", "nappy", "narrow", "nasty", "natural",
		"naughty", "nauseating", "nautical", "near", "neat", "nebulous", "necessary",
		"needless", "needy", "negative", "neglected", "negligible", "neighboring",
		"neighborly", "nervous", "new", "next", "nice", "nifty", "nimble", "nine",
		"nippy", "nocturnal", "noiseless", "noisy", "nonchalant", "nondescript",
		"nonsensical", "nonstop", "normal", "nostalgic", "nosy", "notable", "noted",
		"noteworthy", "novel", "noxious", "null", "numb", "numberless", "numerous",
		"nutritious", "nutty", "oafish", "obedient", "obeisant", "obese", "oblivious",
		"oblong", "obnoxious", "obscene", "obsequious", "observant", "obsolete",
		"obtainable", "obvious", "occasional", "oceanic", "odd", "oddball", "offbeat",
		"offensive", "official", "oily", "old", "old-fashioned", "omniscient", "one",
		"onerous", "only", "open", "opposite", "optimal", "optimistic", "opulent",
		"orange", "orderly", "ordinary", "organic", "original", "ornate", "ornery",
		"ossified", "other", "our", "outgoing", "outlandish", "outlying", "outrageous",
		"outstanding", "oval", "overconfident", "overcooked", "overdue", "overjoyed",
		"overlooked", "overrated", "overt", "overwrought", "painful", "painstaking",
		"palatable", "pale", "paltry", "panicky", "panoramic", "parallel", "parched",
		"parsimonious", "partial", "passionate", "past", "pastel", "pastoral",
		"pathetic", "peaceful", "penitent", "peppery", "perfect", "perfumed",
		"periodic", "perky", "permissible", "perpetual", "perplexed", "personal",
		"pertinent", "pesky", "pessimistic", "petite", "petty", "phobic", "phony",
		"physical", "picayune", "piercing", "pink", "piquant", "pitiful", "placid",
		"plain", "plaintive", "plastic", "plausible", "playful", "pleasant", "pleased",
		"pleasing", "plucky", "plump", "plush", "pointed", "pointless", "poised",
		"polished", "polite", "political", "pompous", "poor", "popular", "portly",
		"posh", "positive", "possessive", "possible", "potable", "powerful",
		"powerless", "practical", "precious", "pregnant", "premium", "present",
		"prestigious", "pretty", "previous", "pricey", "prickly", "primary", "prime",
		"pristine", "private", "prize", "probable", "productive", "profitable",
		"profuse", "proper", "protective", "proud", "prudent", "psychedelic",
		"psychological", "psychotic", "public", "puffy", "pumped", "punctual",
		"pungent", "puny", "pure", "purple", "purring", "pushy", "putrid", "puzzled",
		"puzzling", "quaint", "qualified", "quarrelsome", "quarterly", "queasy",
		"querulous", "questionable", "quick", "quick-witted", "quickest", "quiet",
		"quintessential", "quirky", "quixotic", "quizzical", "rabid", "racial",
		"radiant", "ragged", "rainy", "rambunctious", "rampant", "rapid", "rare",
		"rash", "raspy", "ratty", "raw", "ready", "real", "realistic", "reasonable",
		"rebel", "recent", "receptive", "reckless", "recondite", "rectangular", "red",
		"redundant", "reflecting", "reflective", "regal", "regular", "relevant",
		"reliable", "relieved", "remarkable", "reminiscent", "remorseful", "remote",
		"repentant", "repulsive", "required", "resolute", "resonant", "respectful",
		"responsible", "responsive", "revolving", "rewarding", "rhetorical", "rich",
		"right", "righteous", "rightful", "rigid", "ringed", "ripe", "ritzy", "roasted",
		"robust", "romantic", "roomy", "rosy", "rotating", "rotten", "rotund", "rough",
		"round", "rowdy", "royal", "rubbery", "ruddy", "rude", "rundown", "runny",
		"rural", "rustic", "rusty", "ruthless", "sad", "safe", "salty", "same", "sandy",
		"sane", "sarcastic", "sardonic", "sassy", "satisfied", "satisfying", "savory",
		"scaly", "scandalous", "scant", "scarce", "scared", "scary", "scattered",
		"scented", "scholarly", "scientific", "scintillating", "scornful", "scratchy",
		"scrawny", "screeching", "second", "second-hand", "secondary", "secret",
		"secretive", "sedate", "seemly", "selective", "self-assured", "self-reliant",
		"selfish", "sentimental", "separate", "serene", "serious", "serpentine",
		"several", "severe", "sexual", "shabby", "shadowy", "shady", "shaggy", "shaky",
		"shallow", "shameful", "shameless", "sharp", "shimmering", "shiny", "shivering",
		"shocked", "shocking", "shoddy", "short", "short-term", "showy", "shrill",
		"shut", "shy", "sick", "significant", "silent", "silky", "silly", "silver",
		"similar", "simple", "simplistic", "sincere", "sinful", "single", "six",
		"sizzling", "skeletal", "skillful", "skinny", "sleepy", "slight", "slim",
		"slimy", "slippery", "sloppy", "slow", "slushy", "small", "smarmy", "smart",
		"smelly", "smiling", "smoggy", "smooth", "smug", "snappy", "snarling", "sneaky",
		"sniveling", "snobbish", "snoopy", "snotty", "sociable", "soft", "soggy",
		"solid", "somber", "some", "sophisticated", "sordid", "sore", "sorrowful",
		"sorry", "soulful", "soupy", "sour", "southern", "Spanish", "sparkling",
		"sparse", "special", "specific", "spectacular", "speedy", "spherical", "spicy",
		"spiffy", "spiky", "spirited", "spiritual", "spiteful", "splendid", "spooky",
		"spotless", "spotted", "spotty", "spry", "spurious", "squalid", "square",
		"squeaky", "squealing", "squeamish", "squiggly", "stable", "staid", "stained",
		"staking", "stale", "standard", "standing", "starchy", "stark", "starry",
		"statuesque", "steadfast", "steady", "steel", "steep", "stereotyped", "sticky",
		"stiff", "stimulating", "stingy", "stormy", "stout", "straight", "strange",
		"strict", "strident", "striking", "striped", "strong", "studious", "stunning",
		"stupendous", "stupid", "sturdy", "stylish", "subdued", "submissive",
		"subsequent", "substantial", "subtle", "suburban", "successful", "succinct",
		"succulent", "sudden", "sufficient", "sugary", "suitable", "sulky", "sunny",
		"super", "superb", "superficial", "superior", "supportive", "supreme",
		"sure-footed", "surprised", "suspicious", "svelte", "swanky", "sweaty", "sweet",
		"sweltering", "swift", "sympathetic", "symptomatic", "synonymous", "taboo",
		"tacit", "tacky", "talented", "talkative", "tall", "tame", "tan", "tangible",
		"tangy", "tart", "tasteful", "tasteless", "tasty", "tattered", "taut", "tawdry",
		"tearful", "technical", "tedious", "teeming", "teeny", "teeny-tiny", "telling",
		"temporary", "tempting", "ten", "tender", "tense", "tenuous", "tepid",
		"terrible", "terrific", "tested", "testy", "thankful", "therapeutic", "thick",
		"thin", "thinkable", "third", "thirsty", "thorny", "thorough", "thoughtful",
		"thoughtless", "threadbare", "threatening", "three", "thrifty", "thundering",
		"thunderous", "tidy", "tight", "tightfisted", "timely", "tinted", "tiny",
		"tired", "tiresome", "toothsome", "torn", "torpid", "total", "tough",
		"towering", "traditional", "tragic", "trained", "tranquil", "trashy",
		"traumatic", "treasured", "tremendous", "triangular", "tricky", "trifling",
		"trim", "trite", "trivial", "troubled", "truculent", "true", "trusting",
		"trustworthy", "trusty", "truthful", "tubby", "turbulent", "twin", "two",
		"typical", "ubiquitous", "ugliest", "ugly", "ultimate", "ultra", "unable",
		"unacceptable", "unaccountable", "unarmed", "unaware", "unbecoming", "unbiased",
		"uncomfortable", "uncommon", "unconscious", "uncovered", "understated",
		"understood", "undesirable", "unequal", "unequaled", "uneven", "unfair",
		"unfinished", "unfit", "unfolded", "unfortunate", "unhappy", "unhealthy",
		"uniform", "unimportant", "uninterested", "unique", "united", "unkempt",
		"unknown", "unlawful", "unlikely", "unlined", "unlucky", "unnatural",
		"unpleasant", "unrealistic", "unripe", "unruly", "unselfish", "unsightly",
		"unsteady", "unsuitable", "unsung", "untidy", "untimely", "untried", "untrue",
		"unused", "unusual", "unwelcome", "unwieldy", "unwilling", "unwitting",
		"unwritten", "upbeat", "uppity", "upright", "upset", "uptight", "urban",
		"usable", "used", "useful", "useless", "utilized", "utopian", "utter",
		"uttermost", "vacant", "vacuous", "vague", "vain", "valid", "valuable", "vapid",
		"variable", "various", "vast", "velvety", "venerated", "vengeful", "venomous",
		"verdant", "verifiable", "versed", "vexed", "vibrant", "vicious", "victorious",
		"vigilant", "vigorous", "villainous", "violent", "violet", "virtual",
		"virtuous", "visible", "vital", "vivacious", "vivid", "voiceless", "volatile",
		"voluminous", "voracious", "vulgar", "wacky", "waggish", "waiting", "wakeful",
		"wan", "wandering", "wanting", "warlike", "warm", "warmhearted", "warped",
		"wary", "wasteful", "watchful", "waterlogged", "watery", "wavy", "weak",
		"wealthy", "weary", "webbed", "wee", "weekly", "weepy", "weighty", "weird",
		"well-documented", "well-groomed", "well-informed", "well-lit", "well-made",
		"well-off", "well-to-do", "well-worn", "wet", "which", "whimsical", "whirlwind",
		"whispered", "whispering", "white", "whole", "wholesale", "whopping", "wicked",
		"wide", "wide-eyed", "wiggly", "wild", "willing", "wilted", "winding", "windy",
		"winged", "wiry", "wise", "wistful", "witty", "wobbly", "woebegone", "woeful",
		"womanly", "wonderful", "wooden", "woozy", "wordy", "workable", "worldly",
		"worn", "worried", "worrisome", "worse", "worst", "worthless", "worthwhile",
		"worthy", "wrathful", "wretched", "writhing", "wrong", "wry", "xenophobic",
		"yawning", "yearly", "yellow", "yellowish", "yielding", "young", "youthful",
		"yummy", "zany", "zealous", "zesty", "zippy"
	],
	"adverbs":[
		"abnormally", "aboard", "about", "abroad", "absentmindedly",
		"absolutely", "abundantly", "accidentally", "accordingly", "actively",
		"actually", "acutely", "admiringly", "affectionately", "affirmatively", "after",
		"afterwards", "agreeably", "almost", "already", "always", "amazingly",
		"angrily", "annoyingly", "annually", "anxiously", "anyhow", "anyplace",
		"anyway", "anywhere", "appreciably", "appropriately", "around", "arrogantly",
		"aside", "assuredly", "astonishingly", "away", "awfully", "awkwardly", "barely",
		"bashfully", "beautifully", "before", "begrudgingly", "believably",
		"bewilderedly", "bewilderingly", "bitterly", "bleakly", "blindly", "blissfully",
		"boastfully", "boldly", "boyishly", "bravely", "briefly", "brightly",
		"brilliantly", "briskly", "brutally", "busily", "calmly", "candidly",
		"carefully", "carelessly", "casually", "cautiously", "certainly", "charmingly",
		"cheerfully", "chiefly", "childishly", "cleanly", "clearly", "cleverly",
		"closely", "cloudily", "clumsily", "coaxingly", "coincidentally", "coldly",
		"colorfully", "comfortably", "commonly", "compactly", "compassionately",
		"completely", "confusedly", "consequently", "considerably", "considerately",
		"consistently", "constantly", "continually", "continuously", "coolly",
		"correctly", "courageously", "covertly", "cowardly", "crazily", "crossly",
		"cruelly", "cunningly", "curiously", "currently", "customarily", "cutely",
		"daily", "daintily", "dangerously", "daringly", "darkly", "dastardly", "dearly",
		"decently", "deeply", "defiantly", "deftly", "deliberately", "delicately",
		"delightfully", "densely", "diagonally", "differently", "diligently", "dimly",
		"directly", "disorderly", "divisively", "docilely", "dopily", "doubtfully",
		"down", "dramatically", "dreamily", "during", "eagerly", "early", "earnestly",
		"easily", "efficiently", "effortlessly", "elaborately", "elegantly",
		"eloquently", "elsewhere", "emotionally", "endlessly", "energetically",
		"enjoyably", "enormously", "enough", "enthusiastically", "entirely", "equally",
		"especially", "essentially", "eternally", "ethically", "even", "evenly",
		"eventually", "evermore", "every", "everywhere", "evidently", "evocatively",
		"exactly", "exceedingly", "exceptionally", "excitedly", "exclusively",
		"explicitly", "expressly", "extensively", "externally", "extra",
		"extraordinarily", "extremely", "fairly", "faithfully", "famously", "far",
		"fashionably", "fast", "fatally", "favorably", "ferociously", "fervently",
		"fiercely", "fiery", "finally", "financially", "finitely", "fluently", "fondly",
		"foolishly", "forever", "formally", "formerly", "fortunately", "forward",
		"frankly", "frantically", "freely", "frenetically", "frequently", "fully",
		"furiously", "furthermore", "generally", "generously", "gently", "genuinely",
		"girlishly", "gladly", "gleefully", "gracefully", "graciously", "gradually",
		"gratefully", "greatly", "greedily", "grimly", "grudgingly", "habitually",
		"half-heartedly", "handily", "handsomely", "haphazardly", "happily",
		"harmoniously", "harshly", "hastily", "hatefully", "hauntingly", "healthily",
		"heartily", "heavily", "helpfully", "hence", "highly", "hitherto", "honestly",
		"hopelessly", "horizontally", "hourly", "how", "however", "hugely",
		"humorously", "hungrily", "hurriedly", "hysterically", "icily", "identifiably",
		"idiotically", "imaginatively", "immeasurably", "immediately", "immensely",
		"impatiently", "impressively", "inappropriately", "incessantly", "incorrectly",
		"indeed", "independently", "indoors", "indubitably", "inevitably", "infinitely",
		"informally", "infrequently", "innocently", "inquisitively", "instantly",
		"intelligently", "intensely", "intently", "interestingly", "intermittently",
		"internally", "invariably", "invisibly", "inwardly", "ironically",
		"irrefutably", "irritably", "jaggedly", "jauntily", "jealously", "jovially",
		"joyfully", "joylessly", "joyously", "jubilantly", "judgmentally", "just",
		"justly", "keenly", "kiddingly", "kindheartedly", "kindly", "knavishly",
		"knottily", "knowingly", "knowledgeably", "kookily", "lastly", "late", "lately",
		"later", "lazily", "less", "lightly", "likely", "limply", "lithely", "lively",
		"loftily", "longingly", "loosely", "loudly", "lovingly", "loyally", "luckily",
		"luxuriously", "madly", "magically", "mainly", "majestically", "markedly",
		"materially", "meaningfully", "meanly", "meantime", "meanwhile", "measurably",
		"mechanically", "medically", "menacingly", "merely", "merrily", "methodically",
		"mightily", "miserably", "mockingly", "monthly", "morally", "more", "moreover",
		"mortally", "mostly", "much", "mysteriously", "nastily", "naturally",
		"naughtily", "nearby", "nearly", "neatly", "needily", "negatively", "nervously",
		"never", "nevertheless", "next", "nicely", "nightly", "noisily", "normally",
		"nosily", "not", "now", "nowadays", "numbly", "obediently", "obligingly",
		"obnoxiously", "obviously", "occasionally", "oddly", "offensively",
		"officially", "often", "ominously", "once", "only", "openly", "optimistically",
		"orderly", "ordinarily", "outdoors", "outrageously", "outwardly", "outwards",
		"overconfidently", "overseas", "painfully", "painlessly", "paradoxically",
		"partially", "particularly", "passionately", "patiently", "perfectly",
		"periodically", "perpetually", "persistently", "personally", "persuasively",
		"physically", "plainly", "playfully", "poetically", "poignantly", "politely",
		"poorly", "positively", "possibly", "potentially", "powerfully", "presently",
		"presumably", "prettily", "previously", "primly", "principally", "probably",
		"promptly", "properly", "proudly", "punctually", "puzzlingly", "quaintly",
		"queasily", "questionably", "questioningly", "quicker", "quickly", "quietly",
		"quirkily", "quite", "quizzically", "randomly", "rapidly", "rarely", "readily",
		"really", "reasonably", "reassuringly", "recently", "recklessly", "regularly",
		"reliably", "reluctantly", "remarkably", "repeatedly", "reproachfully",
		"resentfully", "respectably", "respectfully", "responsibly", "restfully",
		"richly", "ridiculously", "righteously", "rightfully", "rightly", "rigidly",
		"roughly", "routinely", "rudely", "ruthlessly", "sadly", "safely", "scarcely",
		"scarily", "scientifically", "searchingly", "secretively",
		"securely", "sedately", "seemingly", "seldom", "selfishly", "selflessly",
		"separately", "seriously", "shakily", "shamelessly", "sharply", "sheepishly",
		"shoddily", "shortly", "shrilly", "shyly", "significantly", "silently",
		"simply", "sincerely", "singularly", "skillfully", "sleepily", "slightly",
		"slowly", "slyly", "smoothly", "so", "softly", "solely", "solemnly",
		"solicitously", "solidly", "somehow", "sometimes", "somewhat", "somewhere",
		"soon", "spasmodically", "specially", "specifically", "spectacularly",
		"speedily", "spiritually", "splendidly", "sporadically", "startlingly",
		"steadily", "stealthily", "sternly", "still", "strenuously", "stressfully",
		"strictly", "structurally", "studiously", "stupidly", "stylishly",
		"subsequently", "substantially", "subtly", "successfully", "suddenly",
		"sufficiently", "suitably", "superficially", "supremely", "surely",
		"surprisingly", "suspiciously", "sweetly", "swiftly", "sympathetically",
		"systematically", "temporarily", "tenderly", "tensely", "tepidly", "terribly",
		"thankfully", "then", "there", "thereby", "thoroughly", "thoughtfully", "thus",
		"tightly", "today", "together", "tomorrow", "too", "totally", "touchingly",
		"tremendously", "truly", "truthfully", "twice", "ultimately", "unabashedly",
		"unanimously", "unbearably", "unbelievably", "unemotionally", "unethically",
		"unexpectedly", "unfailingly", "unfavorably", "unfortunately", "uniformly",
		"unilaterally", "unimpressively", "universally", "unkindly", "unnaturally",
		"unnecessarily", "unquestionably", "unselfishly", "unskillfully", "unwillingly",
		"up", "upbeat", "upliftingly", "upright", "upside-down", "upward", "upwardly",
		"urgently", "usefully", "uselessly", "usually", "utterly", "vacantly",
		"vaguely", "vainly", "valiantly", "vastly", "verbally", "vertically", "very",
		"viciously", "victoriously", "vigilantly", "vigorously", "violently", "visibly",
		"visually", "vivaciously", "voluntarily", "warmly", "weakly", "wearily",
		"weekly", "well", "wetly", "when", "where", "while", "whole-heartedly",
		"wholly", "why", "wickedly", "widely", "wiggly", "wildly", "willfully",
		"willingly", "wisely", "woefully", "wonderfully", "worriedly", "worthily",
		"wrongly", "yearly", "yearningly", "yesterday", "yet", "youthfully",
		"zanily", "zealously", "zestfully", "zestily"
	]
};

function rand(min,max) {
  var min,max,i=1;
  if (min===undefined) {
    min=0;
    if (max===undefined) max=1;
  } else if (max===undefined) {
    max=min;
    min=0;
    i--;
  }
  return Math.floor(Math.random()*(max-min+i))+min;
}

function decline(word,plur) { // by word we mean the entire word data
  if (word[1]==='') word[1]=word[0]+"s";
  return word[plur?1:0];
}

function conjugate(word,tens,plur,part) {
  if (word[1]==='') {
    if (word[0][word[0].length-1]=="e") word[1]=word[0]+"d";
    else word[1]=word[0]+"ed";
  }
  if (word[2]==='') word[2]=word[0]+"s";
  if (word[3]==='') word[3]=word[1];
  if (word[4]==='') {
    if (word[0][word[0].length-1]=="e") word[4]=word[0].slice(0,-1)+"ing";
    else word[4]=word[0]+"ing";
  }
  var id;
  if (part) id=3+tens;
  else if (tens) id=plur*-2+2;
  else {
    if (word[0]=="are"&&plur) id=6;
    else id=1;
  }
  return word[id];
}

function addArticle(word,plur) { // as a string
  var r="";
  if (rand()) { // let's add an article?
    if (plur) {
      if (rand()) r=["those ", "these ", "the "][rand(3)];
    } else {
      if (rand()) r=["this ", "that ", "the "][rand(3)];
      else {
        if (['a', 'e', 'i', 'o', 'u'].indexOf(word[0].toLowerCase())>-1) r="an ";
        else r="a ";
      }
    }
  } else {
    if (plur) r=["no", "zero", "two", "many", "countless"][rand(5)]+" ";
    else r="one ";
  }
  return r+word;
}

function gen(num=1) {

  nounslen=words.nouns.length;
  adjlen=words.adjectives.length;
  verbslen=words.verbs.length;

  for (i=0; i<num; i++) {
    var sen={
      "subject":[],
      "predicate":[]
    },tense,pluralSubject,temp;
    tense=rand(3); // create the tense 0=past 1=present 2=future

    if (rand(2)) temp=0; // to be
    else if (rand()) temp=1; // to have
    else temp=rand(verbslen); // random verb

    sen.predicate=words.verbs[temp]; // add the simple predicate thing
    pluralSubject=rand(); // is our subject plural?

    if (sen.predicate[5]&&(temp<4&&rand(2)<1)) { // add noun object if necessary
      var pluralObject=rand();
      sen.object=addArticle(decline(words.nouns[rand(nounslen)],pluralObject),pluralObject); // create random object
    } else if (temp===0) {
      if (rand()) sen.object=words.adjectives[rand(adjlen)];
      else {
        temp=words.verbs[rand(verbslen)];
        sen.object=conjugate(temp,1,pluralSubject,1);
        if (temp[5]) {
          sen.object+=" ";
          if (rand()) {
            var pluralObject=rand();
            sen.object+=addArticle(decline(words.nouns[rand(nounslen)],pluralObject),pluralObject);
          } else sen.object+=words.adjectives[rand(adjlen)];
        }
      }
    } else if (temp===1) {
      if (rand()) {
        sen.object=conjugate(words.verbs[rand(verbslen)],0,pluralSubject,1);
        if (temp[5]) {
          sen.object+=" ";
          if (rand()) {
            var pluralObject=rand();
            sen.object+=addArticle(decline(words.nouns[rand(nounslen)],pluralObject),pluralObject);
          } else sen.object+=words.adjectives[rand(adjlen)];
        }
      }
      else sen.object="to "+(sen.predicate[0]=="are"?"be":sen.predicate[0]);
    } else {
      sen.object="to "+(sen.predicate[0]=="are"?"be":sen.predicate[0]);
    }
    sen.subject=addArticle(decline(words.nouns[rand(nounslen)],pluralSubject),pluralSubject); // create random subject
    if (tense==2) {
      sen.predicate="will "+(sen.predicate[0]=="are"?"be":sen.predicate[0]);
    }
    else sen.predicate=conjugate(sen.predicate,tense,pluralSubject,0);

    comment=sen.subject+" "+sen.predicate+(sen.object===undefined?'':" "+sen.object)+(rand()?".":"!");
    userid=rand(1,200000)
    typeid=rand(1,5000000)
    types=["movie", "actor"]
    type=types[rand(0,1)]

    console.log("INSERT INTO comments (user_id, comment, type, type_id) VALUES ("+userid+", \""+comment+"\", \""+type+"\", "+typeid+");");
  }
}

var args = process.argv.slice(2)
var num = 1
if (args.length == 1) {
   num = args[0]
}

gen(num);
