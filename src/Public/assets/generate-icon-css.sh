#!/bin/bash
prefix="icons/"
suffix="_white_48dp.svg"
icons_css="icons.css"
dark_mode_icons_css="dark-mode-icons.css"
echo "/* Auto generated CSS icon definition file */" > $icons_css
echo "/* Auto generated CSS icon definition file */" > $dark_mode_icons_css
for file in Icons/*; do
    parsed_str=${file#"$prefix"}
    parsed_str=${parsed_str%"$suffix"}
    skip_suffix="black"
    if [[ "$parsed_str" == *"$skip_suffix"* ]]; then
        echo "SKIP"
    else
        echo "$parsed_str"
        icon_name=$(echo "$parsed_str" | tr '_' '-')
        echo ".$icon_name-icon {" >> $icons_css
        echo "    background-image: url(\"/Static/Icons/"$parsed_str"_black_48dp.svg\");" >> $icons_css
        echo "}" >> $icons_css
        echo ".$icon_name-icon-inverted {" >> $icons_css
        echo "    background-image: url(\"/Static/Icons/"$parsed_str"_white_48dp.svg\");" >> $icons_css
        echo "}" >> $icons_css
        echo ".$icon_name-icon-white {" >> $icons_css
        echo "    background-image: url(\"/Static/Icons/"$parsed_str"_white_48dp.svg\");" >> $icons_css
        echo "}" >> $icons_css
        echo ".$icon_name-icon-black {" >> $icons_css
        echo "    background-image: url(\"/Static/Icons/"$parsed_str"_black_48dp.svg\");" >> $icons_css
        echo "}" >> $icons_css
        echo ".$icon_name-icon {" >> $dark_mode_icons_css
        echo "    background-image: url(\"/Static/Icons/"$parsed_str"_white_48dp.svg\");" >> $dark_mode_icons_css
        echo "}" >> $dark_mode_icons_css
        echo ".$icon_name-icon-inverted {" >> $dark_mode_icons_css
        echo "    background-image: url(\"/Static/Icons/"$parsed_str"_black_48dp.svg\");" >> $dark_mode_icons_css
        echo "}" >> $dark_mode_icons_css
    fi
done
